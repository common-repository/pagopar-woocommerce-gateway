<?php
# No mostramos errores
ini_set('display_errors', 'off');
error_reporting(0);

require_once 'sdk/Pagopar.php';
require_once 'helpers/admin-helper.php';
/* Pagopar Payment Gateway Class */

class Pagopar_Gateway extends WC_Payment_Gateway
{

    public $pedidoPagopar;
    public $origin = null;

    // Setup our Gateway's id, description and other values
    function __construct()
    {
        // The global ID for this Payment method
        //$this->origin = 'WOOCOMMERCE 2.6.8';
        $this->origin = $GLOBALS['version'];
        $this->id = "pagopar";

        $this->method_title = __("Pagopar", 'pagopar');

        $this->method_description = __("Pagopar Plug-in de Gateway de pago para WooCommerce", 'pagopar');

        $this->title = __("Pagopar", 'pagopar');

        $this->icon = null;

        // Bool. Can be set to true if you want payment fields to show on the checkout
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;
        
        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();
        
        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value)
        {
            $this->$setting_key = $value;
        }
        
        // Define user set variables.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->testmode = 'yes' === $this->get_option('testmode', 'no');
        
        
        $this->datos_adicionales = null; 

        // Lets check for SSL
        add_action('admin_notices', array(
            $this,
            'do_ssl_check'
        ));

        add_action('admin_notices', array(
            $this,
            'mostrar_logo_pagopar_admin'
        ));

        add_action('admin_notices', array(
            $this,
            'verificar_cuenta'
        ));
        
      

        // Save settings
        if (is_admin())
        {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
        }
    }
    
      

    public function traerDatosComercio() {

        $comercio = pagoparCurl(
            null,
            null,
            'https://api-plugins.pagopar.com/api/comercios/2.0/datos-comercio/',
            false,
            'DATOS-COMERCIO',
            null);
        return $comercio;
    }

    public function obtenerMediosPagosWS()
    {
        $ConsultPagopar = new ConsultPagopar($this->origin);
        $ConsultPagopar->publicKey = $this->get_option('public_key');
        $ConsultPagopar->privateKey = $this->get_option('private_key');


        $datos['token_publico'] = $ConsultPagopar->publicKey;
        $datos['token'] = sha1($ConsultPagopar->privateKey . 'FORMA-PAGO');
        $datos['token'] = sha1($ConsultPagopar->privateKey . 'FORMA-PAGO');

        # Obtenemos los medios de pago disponibles para el comercio via WS
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api-plugins.pagopar.com/api/forma-pago/1.1/traer",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($datos) ,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => array(
                "Cache-Control: no-cache",
                "Content-Type: application/json"
            ) ,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err)
        {
            $respuesta['respuesta'] = false;
            $respuesta['resultado'] = $err;
        }
        else
        {
            $respuesta['respuesta'] = true;
            $respuesta['resultado'] = $response;
        }

        return $respuesta;

    }

    //End __construct()
    //Build the administration fields for this specific Gateway
    public function init_form_fields()
    {
        #error_reporting(E_ALL);
        #ini_set('display_errors', 'on');
        ini_set('display_errors', 'off');
        error_reporting(0);
        
        $citiesConsultPagopar = new ConsultPagopar($this->origin);
        $citiesConsultPagopar->publicKey = $this->get_option('public_key');
        $citiesConsultPagopar->privateKey = $this->get_option('private_key');



        $expiroCacheCiudades =  pagoparCacheCurl('pagopar_ciudades_json', 'pagopar_ciudades_fecha');

        if ($expiroCacheCiudades===false){
            $cities = json_decode(get_option('pagopar_ciudades_json'));
        }else{
        
            # Se hace la petición a Pagopar
            $cities = $citiesConsultPagopar->getCities();
        
            # Guardamos solo si el JSON no contenga un error (como error de token)
            if ($cities->respuesta===true){
                update_option('pagopar_ciudades_json', json_encode($cities));
                update_option('pagopar_ciudades_fecha', @date('Y-m-d H:i:s'));
            }
        }
        


        $cities_wc_format = array();
        if ($cities->respuesta)
        {
            foreach ($cities->resultado as $city)
            {
                $cities_wc_format[$city
                    ->ciudad] = $city->descripcion;
            }
        }
        else
        {
            $cities_wc_format = get_option('seller_ciudad');
        }
        
        add_action('admin_notices', array(
            $this,
            'mostrar_logo_pagopar_admin'
        ));


        # Si no existe json de formas de pago guardado en la db
        if (trim(get_option('json_forma_pago')) == '' || trim(get_option('json_forma_pago_fecha_actualizacion')) == '')
        {
            $formasPagoWS = $this->obtenerMediosPagosWS();

            if ($formasPagoWS['resultado'] != '')
            {
                $formasPagoJson = $formasPagoWS['respuesta'] == true ? $formasPagoWS['resultado'] : '' ;
                $formasPagoFechaActualizacion = @date('Y-m-d H:i:s');
                update_option('json_forma_pago', $formasPagoJson);
                update_option('json_forma_pago_fecha_actualizacion', $formasPagoFechaActualizacion);
            }

        }
        else
        {

            $fechaActual = new DateTime(@date('Y-m-d H:i:s'));
            $fechaUltimaActualizacion = new DateTime(get_option('json_forma_pago_fecha_actualizacion'));
            $diff = $fechaActual->diff($fechaUltimaActualizacion);

            # Si pasó más de 1 hora
            if ($diff->i > 60)
            {
                $formasPagoWS = $this->obtenerMediosPagosWS();

                if ($formasPagoWS['resultado'] != '')
                {
                    $formasPagoJson = $formasPagoWS['respuesta'] == true ? $formasPagoWS['resultado'] : '';
                    $formasPagoFechaActualizacion = @date('Y-m-d H:i:s');
                    update_option('json_forma_pago', $formasPagoJson);
                    update_option('json_forma_pago_fecha_actualizacion', $formasPagoFechaActualizacion);
                }
            }
        }
        include_once 'pagopar-functions.php';
        include_once 'pagopar-functions-api.php';

        $adminHelper = new AdminHelpers();
        
        # Hacemos la peticion a Pagopar para obtener los comercios hijos solo si está habilitado en la configuracion del plugin
        if ($this->get_option('habilitar_split_billing')==='yes'){
            $comerciosHijosJson = traer_comercios_hijos_asociados($citiesConsultPagopar->publicKey, $citiesConsultPagopar->privateKey);
        }
        
        $horarios = array('08:00:00'=>'08:00', '09:00:00'=>'09:00', '10:00:00'=>'10:00', '11:00:00'=>'11:00', '12:00:00'=>'12:00', '13:00:00'=>'13:00', '14:00:00'=>'14:00', '15:00:00'=>'15:00', '16:00:00'=>'16:00', '17:00:00'=>'17:00', '18:00:00'=>'18:00');

        if ($comerciosHijosJson['respuesta'] === false)
        {
            $comerciosHijosJson = '';
        }
        else
        {
            $comerciosHijosJson = json_encode($comerciosHijosJson);
        }
        #echo $comerciosHijosJson;die();
        $estadosExistentesPedido = wc_get_order_statuses();
        $pp_admin_fields = $adminHelper->getAdminFields($cities_wc_format,
                                                        $comerciosHijosJson,
                                                        $estadosExistentesPedido,
                                                        $formasPagoJson,
                                                        $formasPagoFechaActualizacion, $horarios, obtenerURLPaginaConfirmURL(), obtenerURLPaginaRedireccionamiento());

        $this->form_fields = $pp_admin_fields;
        
       
    }
    

    

    /**
     * Generate Map HTML.
     *
     * @access public
     * @param mixed $key
     * @param mixed $data
     * @since 1.0.0
     * @return string
     */
    public function generate_parragraph_html($key, $data)
    {
        $field = $this->plugin_id . $this->id . '_' . $key;
        $defaults = array(
            'class' => 'pagopar_parragraph',
            'css' => '',
            'custom_attributes' => array() ,
            'desc_tip' => false,
            'description' => '',
            'title' => '',
            'default' => '',
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?><tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                <?php echo $this->get_tooltip_html($data); ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr><?php
        return ob_get_clean();
    }

    /**
     * Generate Map HTML.
     *
     * @access public
     * @param mixed $key
     * @param mixed $data
     * @since 1.0.0
     * @return string
     */
    public function generate_map_html($key, $data)
    {
        $field = $this->plugin_id . $this->id . '_' . $key;
        $defaults = array(
            'class' => 'pagopar_map',
            'css' => '',
            'custom_attributes' => array() ,
            'desc_tip' => false,
            'description' => '',
            'title' => '',
            'default' => '',
        );

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?><tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                <?php echo $this->get_tooltip_html($data); ?>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>aaaaaa<?php echo wp_kses_post($data['title']); ?></span></legend>
                    <button class="<?php echo esc_attr($data['class']); ?>" type="button" name="<?php echo esc_attr($field); ?>" id="<?php echo esc_attr($field); ?>" style="<?php echo esc_attr($data['css']); ?>" <?php echo $this->get_custom_attribute_html($data); ?>><?php echo wp_kses_post($data['title']); ?></button>
                    <?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr><?php
        return ob_get_clean();
    }

    /**
     * get_icon function.
     *
     * @return string
     */
    public function get_icon()
    {
        global $woocommerce;
        $icon_html = '';
        /*
        $icon_html = '<style type="text/css">


</style>

';*/
       
        
        
     
     $arrayFormasPagos = $this->datos_adicionales_agrupados;
     
        
        # Obtenemos la descripcion de la agrupacion
        $descripcion = null;
        if(count((array)$arrayFormasPagos)>0){
            $descripcion = end($arrayFormasPagos);
            $descripcion = $descripcion['descripcion_principal'];
        }
        
        
        if ($this->id==='pagopar_transferencia_bancaria'){
            $icon_html .= '<span class="sub">'.$descripcion.'</span>';
        }else{
            # Se aplican fixes html para conseguir la maquetación deseada, debido a la maquetación que se encuentra en el archivo payment-methd.php
            $icon_html .= '
                <span class="sub">'.$descripcion.'</span>
                </label><!-- fix maquetacion payment-method -->
                <span class="methods_group">';


            foreach ($arrayFormasPagos as $key => $value) {
                    foreach ($value['imagen_principal'] as $key2 => $value2) {
                        $icon_html .= '<span class="method_item"><img src="'.$value2['url'].'" alt=""></span>';
                }
                break;
            }


                $icon_html .= '</span>
                <!-- fix maquetacion payment-method --></label style="display:none;">
            ';
            
        }
        
           
           return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);

    }
    
    /**
     * Get Pagopar logo images
     * @return array of image URLs
     */
    protected function get_icon_image()
    {
        $icon = WC_HTTPS::force_https_url(plugins_url('/images/logo.png', __FILE__));
        return apply_filters('woocommerce_' . $this->id . '_icon', $icon);
    }

    public function calculate_flete($order_id)
    {
        $order = new WC_Order($order_id);

        global $woocommerce;

        //We obtain the items
        $order_items = $order->get_items();

        //Create New Pagopar order
        $this->pedidoPagopar = new Pagopar($order_id, $this->db, $this->origin);

        $payments = WC()
            ->payment_gateways
            ->payment_gateways();
        # Para sobreescribir con datos provenientes de campos alternativos
        $razonSocialAlternativo = $payments['pagopar']->settings['campo_alternativo_razon_social'];
        $rucAlternativo = $payments['pagopar']->settings['campo_alternativo_ruc'];
        $documentoAlternativo = $payments['pagopar']->settings['campo_alternativo_documento'];
        $habilitar_split_billing = $payments['pagopar']->settings['habilitar_split_billing'];
        $porcentaje_comision_comercio_padre = $payments['pagopar']->settings['porcentaje_comision_comercio_padre'];

        //We add the buyer
        $buyer = new BuyerPagopar();
        $buyer->name = $order->billing_first_name . ' ' . $order->billing_last_name;
        $buyer->email = $order->billing_email;
        $buyer->cityId = $_POST['billing_ciudad'];
        $buyer->tel = $order->billing_phone;
        $buyer->typeDoc = 'CI';

        # Si se definio un campo alternativo para documento, usamos ese
        if (($documentoAlternativo != '') and ($documentoAlternativo != 'billing_documento'))
        {
            $buyer->doc = $_POST[$documentoAlternativo];
        }
        else
        {
            $buyer->doc = $_POST['billing_documento'];
        }

        $buyer->addr = $order->billing_address_1;
        $buyer->addRef = $_POST['billing_referencia'];
        $buyer->addrCoo = '';

        # Si se definio un campo alternativo para ruc, usamos ese
        if (($rucAlternativo != '') and ($rucAlternativo != 'billing_ruc'))
        {
            $buyer->ruc = $_POST[$rucAlternativo];
        }
        else
        {
            $buyer->ruc = ($_POST['billing_ruc']) ? $_POST['billing_ruc'] : null;
        }

        # Si se definio un campo alternativo para razon social, usamos ese
        if (($razonSocialAlternativo != '') and ($razonSocialAlternativo != 'billing_razon_social'))
        {
            $buyer->socialReason = $_POST[$razonSocialAlternativo];
        }
        else
        {
            $buyer->socialReason = ($_POST['billing_razon_social']) ? $_POST['billing_razon_social'] : null;
        }

        $this
            ->pedidoPagopar
            ->order
            ->addPagoparBuyer($buyer);

        //We add items
        foreach ($order_items as $product)
        {

            if ((is_numeric($product['variation_id'])) and ($product['variation_id'] > 0))
            {
                $idProductoReal = $product['variation_id'];
            }
            else
            {
                $idProductoReal = $product['product_id'];
            }
            #var_dump($idProductoReal);
            #die();
            # En este caso usamos el Id del producto padre (sin variación) ya que cuando se guardan los datos
            # de pagopar se guarda por este id, no por el id de variacion del producto
            $p_id = $product['product_id'];
            #$p_id = $product['product_id'];
            $phone = get_post_meta($p_id, 'product_seller_phone', true);
            $addr = get_post_meta($p_id, 'product_seller_addr', true);
            $addr_ref = get_post_meta($p_id, 'product_seller_addr_ref', true);
            $coo = get_post_meta($p_id, 'product_seller_coo', true);
            $city = get_post_meta($p_id, 'product_seller_ciudad', true);
            $weight = get_post_meta($p_id, 'product_weight', true);
            $largo = get_post_meta($p_id, 'pagopar_largo', true);
            $ancho = get_post_meta($p_id, 'pagopar_ancho', true);
            $alto = get_post_meta($p_id, 'pagopar_alto', true);
            $retiro_obs = get_post_meta($p_id, 'product_sucursal_obs', true);
            $json_propio = get_post_meta($p_id, 'product_envios_propios', true);

            $comercio_hijo_vendedor_producto = get_post_meta($p_id, 'comercio_hijo_vendedor_producto', true);
            $splitBillingHabilitado = $this->splitBillingHabilitado($habilitar_split_billing, $comercio_hijo_vendedor_producto);
            $montoComision = $this->calcularMontoComisionPadre($product['total'], $porcentaje_comision_comercio_padre);

            $envio_propio = [];

            $item = new ItemPagopar();
            $item->name = $product['name'];
            $item->qty = $product['quantity'];
            $item->price = $product['total'];
            $item->cityId = ($city) ? $city : $this->seller_ciudad;
            $item->desc = $product['name'];
            $item->url_img = get_the_post_thumbnail_url($p_id, 'medium');
            if (is_numeric($this->configuracion_avanzada_id_categoria_defecto))
            {
                $item->category = $this->configuracion_avanzada_id_categoria_defecto;
            }
            else
            {
                $item->category = get_post_meta($p_id, 'pagopar_final_cat', true);
            }

            #$item->productId = $p_id;
            $item->productId = $idProductoReal;

            $item->sellerPhone = ($phone) ? $phone : $this->seller_phone;
            $item->sellerAddress = ($addr) ? $addr : $this->seller_addr;
            $item->sellerAddressRef = ($addr_ref) ? $addr_ref : $this->seller_addr_ref;
            $item->sellerAddressCoo = ($coo) ? $coo : $this->seller_coo;

            if ($splitBillingHabilitado === true)
            {
                $item->sellerPublicKey = $comercio_hijo_vendedor_producto;
            }
            else
            {
                $item->sellerPublicKey = $this->public_key;
            }

            $item->weight = ($weight) ? $weight : '';
            $item->large = ($largo) ? $largo : '';
            $item->width = ($ancho) ? $ancho : '';
            $item->height = ($alto) ? $alto : '';
            $item->retiroObs = ($retiro_obs) ? $retiro_obs : $this->sucursal_obs;
            if ($json_propio)
            {
                $propios = json_decode($json_propio);
                foreach ($propios as $propio)
                {
                    $envio_propio[] = ["tiempo_entrega" => $propio[2], "destino" => $propio[0], "precio" => $propio[1], ];
                }
                $item->propio = $envio_propio;
            }

            if ($splitBillingHabilitado === true)
            {
                $item->comercio_comision = $montoComision;

            }

            $this
                ->pedidoPagopar
                ->order
                ->addPagoparItem($item);
        }

        $this
            ->pedidoPagopar
            ->order->publicKey = $this->public_key;
        $this
            ->pedidoPagopar
            ->order->privateKey = $this->private_key;
        if ($habilitar_split_billing === 'yes')
        {
            $this
                ->pedidoPagopar
                ->order->typeOrder = 'COMERCIO-HEREDADO';
        }
        else
        {
            $this
                ->pedidoPagopar
                ->order->typeOrder = 'VENTA-COMERCIO';
        }
        $this
            ->pedidoPagopar
            ->order->periodOfDaysForPayment = $this->periodOfDaysForPayment;
        $this
            ->pedidoPagopar
            ->order->periodOfHoursForPayment = (int)$this->periodOfHoursForPayment;
        $this
            ->pedidoPagopar
            ->order->desc = ""; #$order->customer_note;
        $json_flete = $this
            ->pedidoPagopar
            ->getMethodsOfShipping();

        return $json_flete;
    }

    public function splitBillingHabilitado($habilitado, $idComercioHijo)
    {
        if (($habilitado === 'yes') and (strlen($idComercioHijo) > 1))
        {
            return true;
        }
        return false;
    }


    public function calcularMontoComisionPadre($montoProducto, $porcentajeComision)
    {
        $montoComision = 0;
        if(is_numeric($porcentajeComision)){
            $montoComision = $montoProducto * ($porcentajeComision / 100);
        }
        return $montoComision;
    }

    /**
     * Get the transaction URL.
     *
     * @param  WC_Order $order
     *
     * @return string
     */
    public function get_transaction_url($order, $soloCalcularFlete = false)
    {

        #ini_set('display_errors', 'on');
        #error_reporting(E_ALL & ~ E_NOTICE);
        //var_dump($order);die();
        global $woocommerce;
        $metodo_seleccionado_post = $_POST['shipping_method'][0];
        $metodo_seleccionado = null;
        $monto_delivery = 0;
        
        # Obtenemos el metodo id de otra forma
        $metodo_seleccionado_post = WC()->session->get( 'chosen_shipping_methods' );
        $metodo_seleccionado_post = $metodo_seleccionado_post[0];



        # Obtenemos la opcion del delivery
        $metodo_seleccionado_opcion = explode(':', $metodo_seleccionado_post);
        $metodo_seleccionado_opcion = $metodo_seleccionado_opcion['1'];
   
        # se define cual metodo de envio se utilizara, hay que rever esto
        if(strpos($metodo_seleccionado_post, "local_pickup") !== false) {
            $metodo_seleccionado = "retiro";
        } else {
            if (strpos($metodo_seleccionado_post, "flat_rate_aex") !== false) {
                $metodo_seleccionado = "aex";
            } elseif (strpos($metodo_seleccionado_post, "flat_rate_mobi") !== false) {
                $metodo_seleccionado = "mobi";
            } else{
                $metodo_seleccionado = "propio";
            }
        }
        
        

        $pagopar_calcular_flete = WC()->session->get('pagopar_order_flete');

        $conAex = $pagopar_calcular_flete !== null;
        $arrayResponse = $pagopar_calcular_flete === null ? null : json_decode($pagopar_calcular_flete);
        
        /* Fix, se comprueba que el json respondido del flete sea ok, ya que se pudo haber activado aex, y luego desactivado, y el cliente ya realizo el endpoint de 
calcular flete y se guarda en sesion*/
        if ($arrayResponse->respuesta === false){
            $conAex = null;
        }
        
        $newOrderPagopar = array();

        //to escape # from order id
        $order_id = trim(str_replace('#', '', $order->get_order_number()));


        $newOrderPagopar['id_pedido_comercio'] = $order_id;

        //We obtain the items
        //var_dump($order);die();
        $order_items = $order->get_items();

        //Instanciates wordpress database
        global $wpdb;
        $db = new DBPagopar(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, "wp_transactions_pagopar", $wpdb->prefix."pagopar_pagos_automaticos", $wpdb->prefix."pagopar_pagos_automaticos_detalle");

        //Create New Pagopar order
        $this->pedidoPagopar = new Pagopar($order_id, $db, $this->origin);

        $payments = WC()
            ->payment_gateways
            ->payment_gateways();
        
        # Agregamos el prefijo al numero de pedido para casos donde ya se utilizaron los ID
        $prefijo_orden_pedido = $payments['pagopar']->settings['prefijo_orden_pedido'];
        $newOrderPagopar['id_pedido_comercio'] = $prefijo_orden_pedido.$order_id;


        # Para sobreescribir con datos provenientes de campos alternativos
        $razonSocialAlternativo = $payments['pagopar']->settings['campo_alternativo_razon_social'];
        $rucAlternativo = $payments['pagopar']->settings['campo_alternativo_ruc'];
        $documentoAlternativo = $payments['pagopar']->settings['campo_alternativo_documento'];
        # Valores por defecto
        $razonSocialDefecto = $payments['pagopar']->settings['valor_defecto_razon_social'];
        $rucDefecto = $payments['pagopar']->settings['valor_defecto_ruc'];
        $habilitar_split_billing = $payments['pagopar']->settings['habilitar_split_billing'];
        $porcentaje_comision_comercio_padre = $payments['pagopar']->settings['porcentaje_comision_comercio_padre'];
        
        //We add the buyer
        $doc = null;
        $ruc = null;
        $nombreCampoRuc = null;
        $nombreCampoRazonSocial = null;
        $socialReason = null;
        # Si se definio un campo alternativo para documento, usamos ese
        if (($documentoAlternativo != '') and ($documentoAlternativo != 'billing_documento'))
        {
            $doc = $_POST[$documentoAlternativo];
            $nombreCampoDocumento = $documentoAlternativo;
        }
        else
        {
            $doc = $_POST['billing_documento'];
            $nombreCampoDocumento = 'billing_documento';
        }
        $addr = $order->shipping_address_1;
        if (trim($addr)==''){
            $addr = $order->billing_address_1;
        }
        $addRef = $_POST['billing_referencia'];
        
        
        
        #temp$addrCoo = '-25.231791140032144, -57.54932910203934'; //temp, hay que agregar el campo dinamicamente y tomar dicho valor

        //$buyer->addr = $order->billing_address_1;
        //$buyer->addRef = $_POST['billing_referencia'];
        //$buyer->addrCoo = '';


        # Si se definio un campo alternativo para ruc, usamos ese
        if (($rucAlternativo != '') and ($rucAlternativo != 'billing_ruc'))
        {
            //$buyer->ruc = $_POST[$rucAlternativo];
            $ruc = $_POST[$rucAlternativo];
            $nombreCampoRuc = $rucAlternativo;
        }
        else
        {
            //$buyer->ruc = ($_POST['billing_ruc']) ? $_POST['billing_ruc'] : null;
            $ruc = ($_POST['billing_ruc']) ? $_POST['billing_ruc'] : null;
            $nombreCampoRuc = 'billing_ruc';
        }

        # Si se definio un campo alternativo para razon social, usamos ese
       if (($razonSocialAlternativo != '') and ($razonSocialAlternativo != 'billing_razon_social'))
        {
            //$buyer->socialReason = $_POST[$razonSocialAlternativo];
            $socialReason = $_POST[$razonSocialAlternativo];
            $nombreCampoRazonSocial = $razonSocialAlternativo;
        }
        else
        {
            //$buyer->socialReason = ($_POST['billing_razon_social']) ? $_POST['billing_razon_social'] : null;
            $socialReason = ($_POST['billing_razon_social']) ? $_POST['billing_razon_social'] : null;
            $nombreCampoRazonSocial = 'billing_razon_social';
        }

        # Valores por defecto de razon social y ruc
        if (trim($socialReason) === '')
        {
            $socialReason = $razonSocialDefecto;
        }

        if (trim($ruc) === '')
        {
            $ruc = $rucDefecto;
        }
        $state_code = "";
        
        #temp falta aplicar el metodo packages destinations para unificar, en teoria igual funciona con este codigo
        $woocommerce_ship_to_destination = get_option('woocommerce_ship_to_destination', true);

        if($woocommerce_ship_to_destination === 'billing_only' || $woocommerce_ship_to_destination === 'billing')
        {
            $state_code = $_POST['billing_state'];
        } else {
            $state_code = $_POST['shipping_state'];
        }

        
        $ciudad_id = str_replace("PY", "", $state_code);

        //echo $woocommerce_ship_to_destination." - ".$ciudad_id;die();
        /*if($ciudad_id=="-ASU"){
            $ciudad_id = 1;
        }*/
        if(empty($ciudad_id)){
            $ciudad_id = 1;
        }

        if($ciudad_id=="-ASU"){
            $ciudad_id = 1;
        }
        //temp
        // verificar si hace falta enviar ciudad ya que si no es aex va a enviar un id que no existe en pagopar, sino, enviar 1 por defecto
        $coordenadas = $_POST['billing_coordenadas'];
        $comprador = array(
            "nombre" => $order->billing_first_name . ' ' . $order->billing_last_name,
            "ciudad"=> $ciudad_id,
            "email"=>$order->billing_email,
            "telefono"=>$order->billing_phone,
            "tipo_documento"=>"CI",
            "documento"=>$doc,
            "direccion"=>$addr,
            "direccion_referencia"=>$addRef,
            "coordenadas"=>$coordenadas,
            "ruc"=>$ruc,
            "razon_social"=>$socialReason
        );
        $newOrderPagopar['comprador'] = $comprador;

        $items = $arrayResponse->compras_items;
        

        $items_nuevo_pedido = [];
        $ids_recurrentes = [];
        
        
        
        
        /**
         * Esto permite que si no tiene couriers tercerizados habiltados, envia los montos de delivery sin el endpoint calcular flete, 
         * ya que no se reemplazan las ciudaddes
         * y esto ocasionara problemas de ID de ciudad
         * 
         */
        # Si no tiene habilitado aex, aplicamos fix temporal
        $metodoEnvioPagoarHabilitado = metodoEnvioPagoarHabilitado();
        

        if ($metodoEnvioPagoarHabilitado!==true){
            $order_shippings = $order->get_shipping_methods();
            foreach ($order_shippings as $shipping)
            {
                $shippingName = $shipping['name'];
                $shippingAmount = $shipping['total'];
                if ($shippingAmount > 0)
                {
                    
                //validamos que el producto no sea virtual ni descargable
                $isVirtual = get_post_meta($item->id_producto, '_virtual', true) === "yes";
                $isDownloable = get_post_meta($item->id_producto, '_downloadable', true) === "yes";


                //Determinar precio del delivery dependiendo del tipo de producto(virtual o descargable) y del numero de linea del item.
                if($item->opciones_envio != null) {
                    foreach ($item->opciones_envio as $metodo => $value) {

                        if ($metodo === 'metodo_aex' && $value->costo > 0) {
                            if ($isVirtual || $isDownloable) {
                                $montoDeliveryItem = 0;
                            } else {
                                $montoDeliveryItem = $monto_delivery;
                            }
                        } else {
                            $montoDeliveryItem = 0;
                        }
                    }
                    
                } else {
                    $montoDeliveryItem = 0;
                }                 
                
                $itemPagopar = array(
                        "nombre"=> $shippingName,
                        "cantidad"=>1,
                        "precio_total"=>$shippingAmount,
                        "ciudad"=>$item->ciudad,
                        "descripcion"=>$item->descripcion,
                        "url_imagen"=>$item->url_imagen,
                        "peso"=>$item->peso,
                        "vendedor_telefono"=>($phone) ? $phone : $this->seller_phone,
                        "vendedor_direccion"=>($addr) ? $addr : $this->seller_addr,
                        "vendedor_direccion_referencia"=>($addr_ref) ? $addr_ref : $this->seller_addr_ref,
                        "vendedor_direccion_coordenadas"=>($coo) ? $coo : $this->seller_coo,
                        "public_key"=>$payments['pagopar']->settings['public_key'],
                        "categoria"=> 909,
                        "id_producto"=>$item->id_producto,
                        "largo"=>$item->largo,
                        "ancho"=>$item->ancho,
                        "alto"=>$item->alto,
                        "envio_seleccionado" => ($isVirtual || $isDownloable) ? false : $metodo_seleccionado,
                        "comercio_comision" => $splitBillingHabilitado === true ? $montoComision : 0
                ); 
                
                /*$tiene_pagos_recurrentes = false;

                $tiene_pagos_recurrentes = get_post_meta($item->id_producto, 'product_subscription_enabled', true) === "yes";
                if ($tiene_pagos_recurrentes) {
                    array_push($ids_recurrentes, $item->id_producto);
                }*/
                array_push($items_nuevo_pedido, $itemPagopar);
                    
                    //$monto_delivery = $shippingAmount;
                }
            }
       }else{
           // var_dump($order);die();
           $order_shippings = $order->get_shipping_methods();
            foreach ($order_shippings as $shipping)
            {
                $shippingName = $shipping['name'];
                $shippingAmount = $shipping['total'];
                if ($shippingAmount > 0)
                {
                    $monto_delivery = $shippingAmount;
                }
            }
       }
        
        

        //Crear la lista de items dependiendo de si hay o no  productos fisicos en el carrito
        if($conAex) {
                $metodos_propios = WC()->session->get('metodos_envios_flete');
                $retiroLocal = WC()->session->get('metodos_envios_retiro_local');

                $metodos_propio_array = json_decode($metodos_propios, true);
                $metodos_retiro_local_array = json_decode($retiroLocal, true);
                /*temp$metodo_propio= [];
                foreach($metodos_propio_array as $propio){
                  $propio = array(
                    "tiempo_entrega" => $propio->tiempo_entrega,
                    "destino" => $propio->destino,
                    "precio" => floatval($propio->precio)
                  );
                  array_push($metodo_propio, $propio);
                  
                }*/
		
			
			//die();

            foreach ($items as $item) {
                $metodos = array();
                $metodos = $item->opciones_envio;
				$metodos = (array) $metodos;
							//var_dump($metodos);

                array_push($metodos, array(
                        "metodo_propio" => array(
                            "listado" => $metodos_propio_array
                        )
                ));
				
			
                
                array_push($metodos, array(
                        "metodo_retiro" =>  $metodos_retiro_local_array
                ));
                
                
                $montoComision = $this->calcularMontoComisionPadre($item->precio_total, $porcentaje_comision_comercio_padre);
                
                //validamos que el producto no sea virtual ni descargable
                $isVirtual = get_post_meta($item->id_producto, '_virtual', true) === "yes";
                $isDownloable = get_post_meta($item->id_producto, '_downloadable', true) === "yes";


                //Determinar precio del delivery dependiendo del tipo de producto(virtual o descargable) y del numero de linea del item.
                if($item->opciones_envio != null) {
                    foreach ($item->opciones_envio as $metodo => $value) {

                        if ($metodo === 'metodo_aex' && $value->costo > 0) {
                            if ($isVirtual || $isDownloable) {
                                $montoDeliveryItem = 0;
                            } else {
                                $montoDeliveryItem = $monto_delivery;
                            }
                        } else {
                            $montoDeliveryItem = 0;
                        }
                    }
                    
                } else {
                    $montoDeliveryItem = 0;
                }
                

                #FIX PARA MICROLIDER
                $plugin_envio_microlider_instalado = 0;
                $total_envio_microlider = 0;
                $plugin_envio_microlider = class_exists( 'WC_Custom_Shipping_Method' );
                if($plugin_envio_microlider){
                    $plugin_envio_microlider_instalado = 1;
                    $order_id = $order->get_id();
                    $valor_propio = $order->get_shipping_methods();
                    foreach($valor_propio as $vvv){
                        $total_envio_microlider = $vvv['total'];
                    
                    }
                }

                if($item->opciones_envio != null) {
                    foreach ($item->opciones_envio as $metodo => $value) {
                
                        
                        if ($isVirtual || $isDownloable) {
                                $montoDeliveryItem = 0;
                        } else {
                            
                            # si selecciono aex
                            if (($metodo_seleccionado==='aex') and ($metodo==='metodo_aex')){
                                $montoDeliveryItem = 0;
                                foreach ($value->opciones as $keyMP => $valueMP) {
                                   
                                    if ($metodo_seleccionado_opcion === $valueMP->id){
                                       $montoDeliveryItem = $montoDeliveryItem + $valueMP->costo;
                                       $tiempoEntrega = $valueMP->tiempo_entrega;
                                    }
                                }
                            }elseif (($metodo_seleccionado==='mobi') and ($metodo==='metodo_mobi')){
                                $montoDeliveryItem = 0;
                                foreach ($value->opciones as $keyMP => $valueMP) {
                                   
                                    if ($metodo_seleccionado_opcion === $valueMP->id){
                                       $montoDeliveryItem = $montoDeliveryItem + $valueMP->costo;
                                    }
                                }
                            }elseif (($metodo_seleccionado==='propio') and ($metodo==='metodo_propio')){

                                #FIX PARA MICROLIDER
                            if($plugin_envio_microlider_instalado){
                                $montoDeliveryItem = $total_envio_microlider;
                                $tiempoEntrega = $value->tiempo_entrega;
                            }else{

                                $montoDeliveryItem = $value->costo;
                                $tiempoEntrega = $value->tiempo_entrega;

                            }

                            }elseif (($metodo_seleccionado==='retiro') and ($metodo==='metodo_retiro')){
                                $montoDeliveryItem = $value->costo;
                                $tiempoEntrega = $value->tiempo_entrega;
                            }else{
                              /*  
                                foreach ($value->opciones as $keyMP => $valueMP) {
                                }
                                
                                echo 'obtener costo envio propio';
                                $montoDeliveryItem = 0;
                                echo 'zzzz';*/
                            }
                            
                        }
                        
                  
                    }
                    
                } else {
                    $montoDeliveryItem = 0;
                }
                

                
                
                # Obtenemos los datos de la direccion asociada al producto
                $direccionProducto = obtenerDireccionProducto($item->id_producto);       
                $phone = $direccionProducto->telefono;
                $addr = $direccionProducto->direccion;
                $addr_ref = $direccionProducto->direccion_referencia;
                $coo = $direccionProducto->direccion_coordenadas;

                # Parche mientras se habilite descuento en API 2.0 en Pagopar
                 $descripcionDescuento = '';
                 $subtotal = $product['total'];

                 $subtotal = $item->precio_total;

                 #tipo de cambio
                 @$exchange_rate = get_option('exchange_rate');
                 if(empty($exchange_rate)){
                    $exchange_rate = 1;
                 }else{
                    $exchange_rate = get_option('exchange_rate');
                 }

                 #comision cliente
                 @$commission_percentage = get_option('commission_percentage');
                 if(empty($commission_percentage)){
                    $commission_percentage = 1;
                 }else{
                    $commission_percentage = get_option('commission_percentage');
                 }


                // var_dump($exchange_rate);die();
                 $subtotal = $item->precio_total * $exchange_rate * $commission_percentage;

                 $totalDescuento = 0;

                //$item->precio_total,
                //1

                $itemPagopar = array(
                        "nombre"=>$item->nombre,
                        "cantidad"=>$item->cantidad,
                        "precio_total"=>$subtotal,
                        "ciudad"=>$item->ciudad,
                        "descripcion"=>$item->descripcion,
                        "url_imagen"=>$item->url_imagen,
                        "peso"=>$item->peso,
                        "vendedor_telefono"=>($phone) ? $phone : $this->seller_phone,
                        "vendedor_direccion"=>($addr) ? $addr : $this->seller_addr,
                        "vendedor_direccion_referencia"=>($addr_ref) ? $addr_ref : $this->seller_addr_ref,
                        "vendedor_direccion_coordenadas"=>($coo) ? $coo : $this->seller_coo,
                        "public_key"=>$payments['pagopar']->settings['public_key'],
                        "categoria"=>$item->categoria,
                        "id_producto"=>$item->id_producto,
                        "largo"=>$item->largo,
                        "ancho"=>$item->ancho,
                        "alto"=>$item->alto,
                        "opciones_envio" => $item->opciones_envio,
                        "costo_envio" =>  floatval($montoDeliveryItem),
                        "envio_seleccionado" => ($isVirtual || $isDownloable) ? false : $metodo_seleccionado,
                        "comercio_comision" => $splitBillingHabilitado === true ? $montoComision : 0
                ); 
                
                if ($metodo_seleccionado==='aex'){
                    $itemPagopar['opciones_envio']->metodo_aex->id = $metodo_seleccionado_opcion;
                    $itemPagopar['opciones_envio']->metodo_aex->tiempo_entrega = $tiempoEntrega;
                    $itemPagopar['opciones_envio']->metodo_aex->costo = $montoDeliveryItem;
                }elseif ($metodo_seleccionado==='mobi'){
                    $itemPagopar['opciones_envio']->metodo_mobi->id = $metodo_seleccionado_opcion;
                    $itemPagopar['opciones_envio']->metodo_mobi->costo = $montoDeliveryItem;
                }
                

                $tiene_pagos_recurrentes = false;

                $tiene_pagos_recurrentes = get_post_meta($item->id_producto, 'product_subscription_enabled', true) === "yes";
                if ($tiene_pagos_recurrentes) {
                    array_push($ids_recurrentes, $item->id_producto);
                }
                array_push($items_nuevo_pedido, $itemPagopar);

            }
        } else {
            $metodo_seleccionado = false;
            
            
            
            foreach ($order_items as $product)
            {
                //var_dump($product);die();
                if ((is_numeric($product['variation_id'])) and ($product['variation_id'] > 0))
                {
                    $idProductoReal = $product['variation_id'];
                }
                else
                {
                    $idProductoReal = $product['product_id'];
                }
                    # En este caso usamos el Id del producto padre (sin variación) ya que cuando se guardan los datos
                    # de pagopar se guarda por este id, no por el id de variacion del producto
                    $p_id = $product['product_id'];
                    #$p_id = $product['product_id'];
                    $phone = get_post_meta($p_id, 'product_seller_phone', true);
                    $addr = get_post_meta($p_id, 'product_seller_addr', true);
                    $addr_ref = get_post_meta($p_id, 'product_seller_addr_ref', true);
                    $coo = get_post_meta($p_id, 'product_seller_coo', true);
                    $city = get_post_meta($p_id, 'product_seller_ciudad', true);
                    $weight = get_post_meta($p_id, 'product_weight', true);
                    $largo = get_post_meta($p_id, 'pagopar_largo', true);
                    $ancho = get_post_meta($p_id, 'pagopar_ancho', true);
                    $alto = get_post_meta($p_id, 'pagopar_alto', true);
                    $retiro_obs = get_post_meta($p_id, 'product_sucursal_obs', true);
                    $json_propio = get_post_meta($p_id, 'product_envios_propios', true);
                    $comercio_hijo_vendedor_producto = get_post_meta($p_id, 'comercio_hijo_vendedor_producto', true);
                    $splitBillingHabilitado = $this->splitBillingHabilitado($habilitar_split_billing, $comercio_hijo_vendedor_producto);
                    $montoComision = $this->calcularMontoComisionPadre($product['total'], $porcentaje_comision_comercio_padre);
                    
                    
                    /*
                     * Comentamos todo lo referente a cupones ya que woocommerce retorna correctamente en los tres casos (fixed_product/fixed_cart/percent) cuando no se usa aex 
                     */
                    # verificar si utilizar cart o order
                    /*$totalDescuento = intval(WC()->cart->get_discount_total());
                    $totalDescuentoRestante = intval($totalDescuento);
                    
                    # Obtenemos el cupon aplicado para obtener el tipo de descuento fixed_product/fixed_cart/percent
                    $tipoDescuento = WC()->cart->get_applied_coupons();
                    $tipoDescuento = $tipoDescuento[0];
                    $c = new WC_Coupon($tipoDescuento);*/
                    
                    /*echo "Discount Amount ".$c->amount."<br>";//Get Discount amount
echo "Discount Type ".$c->discount_type."<br>";//Get type of discount
echo "Individual Use ".$c->individual_use."<br>";//Get individual use status
echo "Usage Count ".$c->usage_count."<br>";//Get number of times the coupon has been used
echo "Uage Limit ".$c->usage_limit."<br>";//Get usage limit
echo "Coupon Description ".$c->description."<br>";//Get coupon description
                    
                    die();*/

                    /*if (($c->discount_type==='fixed_product') or ($c->discount_type==='fixed_cart')){
                        $totalDescuento = 0;
                        $totalDescuentoRestante = 0;
                    }*/
                    

                    # Parche mientras se habilite descuento en API 2.0 en Pagopar
                    $descripcionDescuento = '';
                   // echo "TOTAL: ".$product['total'];die();
                //var_dump($product);die();
                    $subtotal = $product['total'];
                    /*# SI hay un descuento pendiente por restar para que cuadren los números
                    if ($totalDescuentoRestante > 0){
                        $subtotal = $subtotal - $totalDescuentoRestante;
                        # Para ver si se necesita volver a descontar en el siguiente item
                        $totalDescuentoRestante = $subtotal - $totalDescuentoRestante;
                        $descripcionDescuento = ' menos descuento';

                    }*/

                    
                    
                    # Obtenemos los datos de la direccion asociada al producto
                    $direccionProducto = obtenerDireccionProducto($item->id_producto);       
                    $phone = $direccionProducto->telefono;
                    $addr = $direccionProducto->direccion;
                    $addr_ref = $direccionProducto->direccion_referencia;
                    $coo = $direccionProducto->direccion_coordenadas;

                    # Si es flotante, usamos así para que ya que 1.00 es considerado float
                    $adicionalNombreCantidad = '';
                    $cantidad_pagopar_int = 0;
                    if ((fmod($product['quantity'], 1) !== 0.00)===true){

                            //$product['quantity']=1;
                            $cantidad_pagopar_int = 1;
                            $adicionalNombreCantidad = ' - Cantidad: '.$product['quantity'];
                    }

                     if(empty($cantidad_pagopar_int)){
                        $cantidad_pagopar_int = 1;
                     }

                    $itemPagopar = array(
                        "nombre"=>$product['name'] . $adicionalNombreCantidad,
                        "cantidad"=>$cantidad_pagopar_int,
                        "precio_total"=>$subtotal,
                        "ciudad"=>($city) ? $city : $this->seller_ciudad,
                        "descripcion"=>$product['name'] . $descripcionDescuento,
                        "url_imagen"=>urldecode(get_the_post_thumbnail_url($p_id, 'medium')),
                        "peso"=>($weight) ? $weight : '',
                        "vendedor_telefono"=>($phone) ? $phone : $this->seller_phone,
                        "vendedor_direccion"=>($addr) ? $addr : $this->seller_addr,
                        "vendedor_direccion_referencia"=>($addr_ref) ? $addr_ref : $this->seller_addr_ref,
                        "vendedor_direccion_coordenadas"=>($coo) ? $coo : $this->seller_coo,
                        "public_key"=> $splitBillingHabilitado === true ? $comercio_hijo_vendedor_producto : $payments['pagopar']->settings['public_key'],
                        "categoria"=>909,
                        "id_producto"=>$idProductoReal,
                        "largo"=>($largo) ? $largo : '',
                        "ancho"=>($ancho) ? $ancho : '',
                        "alto"=>($alto) ? $alto : '',
                        "comercio_comision" => $splitBillingHabilitado === true ? $montoComision : 0
                    );
                    
                $tiene_pagos_recurrentes = false;
                
                $tiene_pagos_recurrentes = get_post_meta($item->id_producto, 'product_subscription_enabled', true) === "yes";
                if ($tiene_pagos_recurrentes) {
                    array_push($ids_recurrentes, $item->id_producto);
                }
                array_push($items_nuevo_pedido, $itemPagopar);

            }
        
            
            # Sobreescribimos item pagopar si tien el plugin Deposits instalado ya que se obtienen los datos de otra forma
            /*$itemPagopar = $this->compatibilidadWoocommerceDepositsItemPagopar();
            if (is_array($itemPagopar)){
                 array_push($items_nuevo_pedido , $itemPagopar);                
            }*/
            
        }
       
        
        // Obtenemos costo de envio de Woocommerce


        // Obtenemos costo de impuesto de Woocommerce si no esta instalado el plugin woocommerce deposits, ya que crea conflicto cuando esta habilitado porque tomo como fee

        $woocommerceDepositsInstalado = $this->pluginInstalado('woocommerce-deposits');
        if ($woocommerceDepositsInstalado===false){
            
            $order_fees = $order->get_fees();
            foreach ($order_fees as $fee)
            {

                $feeName = $fee['name'];
                $feeAmount = $fee['amount'];
                
                if ($feeAmount>0){
                    $itemPagopar = array(
                    "nombre"=>$feeName,
                    "cantidad"=>1,
                    "precio_total"=>$feeAmount,
                    "ciudad"=>1,
                    "descripcion"=>$feeName,
                    "url_imagen"=>'',
                    "vendedor_telefono"=>($phone) ? $phone : $this->seller_phone,
                    "vendedor_direccion"=>($addr) ? $addr : $this->seller_addr,
                    "vendedor_direccion_referencia"=>($addr_ref) ? $addr_ref : $this->seller_addr_ref,
                    "vendedor_direccion_coordenadas"=>($coo) ? $coo : $this->seller_coo,
                    "public_key"=>$payments['pagopar']->settings['public_key'],
                    "categoria"=>909,
                    "id_producto"=>"fee_rate_id".$p_id
                    );
                array_push($items_nuevo_pedido , $itemPagopar);

                }
            }
        }

        // Se obtienen los taxes configurados en Woocommerce - ajustes -impuestos
        $order_taxes = $order->get_tax_totals();

        $pagopar_no_agregar_impuestos = get_option('pagopar_no_agregar_impuestos');

        # Si no tiene forzada la opcion de no agregar impuestos, esto se debe a que ciertas personas, aparentemente utilizan los taxes como un monto de recargo, lo cual hace que se sume dos veces
        if ($pagopar_no_agregar_impuestos!=='1'){
            foreach ($order_taxes as $tax)
            {
            $taxAmount = $tax->amount;
            $taxName = $tax->label;
            $itemPagopar = array(
                    "nombre"=>$taxName,
                    "cantidad"=>1,
                    "precio_total"=>$taxAmount,
                    "ciudad"=>1,
                    "descripcion"=>$feeName,
                    "url_imagen"=>'',
                    "peso"=>'',
                    "vendedor_telefono"=>($phone) ? $phone : $this->seller_phone,
                    "vendedor_direccion"=>($addr) ? $addr : $this->seller_addr,
                    "vendedor_direccion_referencia"=>($addr_ref) ? $addr_ref : $this->seller_addr_ref,
                    "vendedor_direccion_coordenadas"=>($coo) ? $coo : $this->seller_coo,
                    "public_key"=>$payments['pagopar']->settings['public_key'],
                    "categoria"=>909,
                    "id_producto"=>"tax_rate_id-" . $tax->rate_id,
                    "largo"=>'',
                    "ancho"=>'',
                    "alto"=>'',
                    "opciones_envio" => null,
                    "costo_envio" =>$monto_delivery,
                    "envio_seleccionado" => $metodo_seleccionado
            );
                array_push($items_nuevo_pedido, $itemPagopar);
            }
        }

       
        $formaPagoFinal = $this->obtenerIDMetodoPagoFinal();


        $newOrderPagopar['compras_items'] = $items_nuevo_pedido;
        $newOrderPagopar['public_key'] = $payments['pagopar']->settings['public_key'];
        $newOrderPagopar['forma_pago'] = $formaPagoFinal;


        if ($habilitar_split_billing === 'yes')
        {
            $newOrderPagopar['tipo_pedido'] = 'COMERCIO-HEREDADO';
        }
        else
        {
            $newOrderPagopar['tipo_pedido'] = 'VENTA-COMERCIO';
        }
   
        
        //$this
        //    ->pedidoPagopar
        //    ->order->desc = ""; #$order->customer_note;

        date_default_timezone_set("America/Asuncion");

         if($_POST['payment_method']=="pagopar_tarjetas_promocion"){
            if(isset($_POST["id_promocion"]) && !empty($_POST["id_promocion"])) {
                global $wpdb;
                $tabla = $wpdb->prefix . 'pagopar_promociones_tarjetas';
    
                $sql = "SELECT * 
                FROM $tabla 
                WHERE estado=1 AND
                id= ".$_POST['id_promocion']." 
                ORDER BY id DESC";
    
                $results_promo = $wpdb->get_results($sql, ARRAY_A);
    
                $porcentaje_decuento = 1;
                if($results_promo){
                    foreach ($results_promo as $row_promo) {
                        $porcentaje_decuento = $row_promo['porcentaje'];
    
                        if($row_promo['tipo']=="Tarjeta"){
                            $id_tipo=9;
                        }else{
                            $id_tipo=24;
                        }
                        $promociones=array($id_tipo=>$row_promo['codigo_promocion']);
                        $datos_adicionales_p = array("promociones"=>$promociones);
                        $newOrderPagopar['forma_pago']=$id_tipo;
                        $newOrderPagopar['datos_adicionales'] = $datos_adicionales_p;
                    }
                }
    
                $total_detalle=0;
                $cantidad_detalle = 0;
                $capturar_descripcion_first = 0;
                $descripcion_detalle=null;
                $precioAnterior = 0;
                foreach($newOrderPagopar['compras_items'] as $valor_item){
                    $cantidad_detalle++;
    
                    if($capturar_descripcion_first==0){
                        $descripcion_detalle = $valor_item['nombre'];
                        $capturar_descripcion_first = 1 ;
                    }
                    $total_detalle = $total_detalle + ($valor_item['cantidad']*$valor_item['precio_total']);
                    $precioAnterior = $precioAnterior + ($valor_item['cantidad']*$valor_item['precio_total']);
                }
                //echo "TATL DEA..".$porcentaje_decuento;die();
                $total_detalle = $total_detalle - (($total_detalle*$porcentaje_decuento)/100);
                $totalDescuento = (($precioAnterior*$porcentaje_decuento)/100);
                $newOrderPagopar['compras_items'] = [];
    
                //echo "TOTAL DESC. :".$totalDescuento;die();
    
                if($cantidad_detalle>1){
                    $descripcion_detalle = $descripcion_detalle." y otros productos mas..";
                }
    
                $itemPagopar = array(
                    "nombre"=>$descripcion_detalle,
                    "cantidad"=>1,
                    "precio_total"=>$total_detalle,
                    "ciudad"=>1,
                    "descripcion"=>'<p><b>Precio anterior:</b> <del>'.number_format($precioAnterior).' Gs.</del></p><p> <b>Precio con descuento:</b> '.number_format($total_detalle).' Gs.</p>',
                    "url_imagen"=>urldecode(get_the_post_thumbnail_url($p_id, 'medium')),
                    "peso"=>($weight) ? $weight : '',
                    "vendedor_telefono"=>($phone) ? $phone : $this->seller_phone,
                    "vendedor_direccion"=>($addr) ? $addr : $this->seller_addr,
                    "vendedor_direccion_referencia"=>($addr_ref) ? $addr_ref : $this->seller_addr_ref,
                    "vendedor_direccion_coordenadas"=>($coo) ? $coo : $this->seller_coo,
                    "public_key"=> $splitBillingHabilitado === true ? $comercio_hijo_vendedor_producto : $payments['pagopar']->settings['public_key'],
                    "categoria"=>909,
                    "id_producto"=>null,
                    "largo"=>($largo) ? $largo : '',
                    "ancho"=>($ancho) ? $ancho : '',
                    "alto"=>($alto) ? $alto : '',
                    "comercio_comision" => $splitBillingHabilitado === true ? $montoComision : 0
                );
                
                $newOrderPagopar['monto_total'] = $total_detalle;
               array_push($newOrderPagopar['compras_items'], $itemPagopar);
    
               
            }
        }


        //Transformamos el día a horas
        $daysToHours = ($this->periodOfDaysForPayment) ? ($this->periodOfDaysForPayment * 24) : 0;
        $date = date("Y-m-d H:i:s", mktime(date("H") + intval($this->periodOfHoursForPayment) + intval($daysToHours), date("i"), date("s"), date("m"), date("d"), date("Y")));
        $newOrderPagopar['fecha_maxima_pago'] = $date;
        $newOrderPagopar['descripcion_resumen'] = "";
        
        $json = get_post_meta($order_id, 'pagopar_json_selected', true);
        $this->pagopar_actualizar_orden_envio($order_id);

        # Actualizamos los valores de razon social /  ruc
        /*$order->update_meta_data($nombreCampoDocumento, $doc);
        $order->update_meta_data($nombreCampoRazonSocial, $socialReason);
        $order->update_meta_data($nombreCampoRuc, $ruc);
        $order->update_meta_data('billing_coordenadas', $_POST['billing_coordenadas']);*/
				
		$nombreCampoDocumentoDefinido = get_post_meta($order_id, $nombreCampoDocumento, true);
		/*if (trim($nombreCampoDocumentoDefinido)==''){
	 		add_post_meta($order_id, $nombreCampoDocumento, $doc);
		}else{
			update_post_meta($order_id, $nombreCampoDocumento, $doc);
		}*/
		
 		update_post_meta($order_id, $nombreCampoDocumento, $doc);
        update_post_meta($order_id, $nombreCampoRazonSocial, $socialReason);
        update_post_meta($order_id, $nombreCampoRuc, $ruc);
        update_post_meta($order_id, 'billing_coordenadas', $_POST['billing_coordenadas']);		
		
        $user = wp_get_current_user();
        # Actualizamos razon social y ruc por defecto        
        @update_user_meta($user->id, 'pagopar_documento', $doc);
        @update_user_meta($user->id, 'pagopar_razon_social', $socialReason);
        @update_user_meta($user->id, 'pagopar_ruc', $ruc);		
		
		

        #$customer_order->save;
        // Mark order as Paid
        #$customer_order->payment_complete();
        // Empty the cart (Very important step)
        $emptyCartPagopar = $payments['pagopar']->settings['disabled_clear_cart'];

        global $current_user;
        get_currentuserinfo();

        $user_id = $current_user->ID;
        $cart_contents = $woocommerce->cart->get_cart();
        $meta_key = 'cart-'.date('l dS F');
        $meta_value = $cart_contents;
        update_user_meta( $user_id, $meta_key, $meta_value);

        $cart_content=get_user_meta($user_id,$meta_key,true);
        
        
        
        
        # Debe estar comentado, solo se usa para hacer testing
        /*$newOrderPagopar['monto_total'] = $this->getTotalAmount($items_nuevo_pedido) + $monto_delivery - $totalDescuento;
        
        $newOrderPagopar['token'] = $this->generateOrderHash($newOrderPagopar['id_pedido_comercio'], 
                                                         $newOrderPagopar['monto_total'], 
                                                         $payments['pagopar']->settings['private_key']);
        
         # Compatibilidad con el plugin Woocommerce Deposits
        $newOrderPagopar = $this->compatibilidadWoocommerceDepositsJsonIniciarTransaccionPagopar($newOrderPagopar, $order_id);
        die('aa');*/
        # Fin - Debe estar comentado, solo se usa para hacer testing
       


        #temp descomente la linea de arriba, verificar si es correcto

        if (isset($emptyCartPagopar) && $emptyCartPagopar === "no") {
            // add cart contents
            foreach ( $cart_content as $cart_item_key => $values )
            {
                $id =$values['product_id'];
                $quant=$values['quantity'];
                $woocommerce->cart->add_to_cart( $id, $quant);
            }
        }
           
        // Payment has been successful
        $estadoPagopar = $payments['pagopar']->settings['estado_creacion_pedido_pagopar'];
        if (substr($estadoPagopar, 0, 3) === 'wc-')
        {
            $estadoPagopar = substr($estadoPagopar, 3);
        }
        if ($estadoPagopar == '')
        {
            $estadoPagopar = 'processing';
        }
        
        
        $newOrderPagopar['monto_total'] = $this->getTotalAmount($items_nuevo_pedido) + $monto_delivery - $totalDescuento;
        
        $newOrderPagopar['token'] = $this->generateOrderHash($newOrderPagopar['id_pedido_comercio'], 
                                                         $newOrderPagopar['monto_total'], 
                                                         $payments['pagopar']->settings['private_key']);
        $tokenIniciarTransaccion = $newOrderPagopar['token'];



        $order->update_status($estadoPagopar, 'Procesando pedido (No pagado).');
        

        # Si se utiliza couriers de Pagopar
        if($conAex) {
            # Sobreescribimos ciertos valores para hacer calcular envio
            $seleccionarEnvioFlete = $newOrderPagopar;        
            $seleccionarEnvioFlete['token'] = sha1($payments['pagopar']->settings['private_key']."CALCULAR-FLETE");        
            $resultadoSeleccionarFlete = $this->runCurl($seleccionarEnvioFlete, 'https://api-plugins.pagopar.com/api/calcular-flete/2.0/traer');

            # Sobreescribimos de vuelta el token nuevamente para iniciar la transaccion
            $newOrderPagopar = json_decode($resultadoSeleccionarFlete, true);
            $newOrderPagopar['token'] = $tokenIniciarTransaccion;
        }

        // clear current cart, incase you want to replace cart contents, else skip this step

        #aca borra el carrito cuando ocurre un error, y se debe re hacer todo el proceso de compra

        # Compatibilidad con el plugin Woocommerce Deposits
        //$newOrderPagopar = $this->compatibilidadWoocommerceDepositsJsonIniciarTransaccionPagopar($newOrderPagopar, $order_id);

        $resultado = $this->runCurl($newOrderPagopar, 'https://api-plugins.pagopar.com/api/comercios/2.0/iniciar-transaccion');
        $response = json_decode($resultado);
        if ($response->respuesta === false)
        {
            WC()->session->set('pagopar_order_flete' , null);
            WC()->session->set('metodos_envios_flete' , null);
            WC()->session->set('metodos_envios_retiro_local' , null);
            wc_add_notice('Ocurrió un error al realizar la transacción: ' . $response->resultado, 'error');
            return null;
        }

        $hashOrder = $response->resultado[0]->data;

        #temp: Usamos de forma temporal variable $order_id en lugar de $newOrderPagopar['id_pedido_comercio']

        $db->insertTransaction(
                $order_id, $newOrderPagopar['tipo_pedido'], $newOrderPagopar['monto_total'], $hashOrder, $newOrderPagopar['fecha_maxima_pago'], $newOrderPagopar['descripcion_resumen']
        );

        $woocommerce->cart->empty_cart();
        #temp comentamos temporalmente la funcion de debito automatico
        /*if(isset($ids_recurrentes)) {
            $db->insertSubscription($newOrderPagopar['id_pedido_comercio'], date("Y/m/d"), $user_id, true);
            foreach ($ids_recurrentes as $id)
            {
                $cantidad_pagos = get_post_meta($id, 'product_suscription_quantity', true);
                $periodicidad = get_post_meta($id, 'product_subscription_date', true);

                $fecha = date("Y/m/d");

                if ($periodicidad == 7) {
                    $fecha = date('Y/m/d', strtotime("+7 days", strtotime($fecha)));
                } else if ($periodicidad == 30) {
                    $fecha = date('Y/m/d', strtotime("+1 months", strtotime($fecha)));
                }
                $db->insertSubscriptionDetail($newOrderPagopar['id_pedido_comercio'], $id, 0, date("Y/m/d"),$cantidad_pagos, 1, $fecha);
            }
        }*/
        
        $formaPagoFinal = $this->obtenerIDMetodoPagoFinal();

        WC()->session->set('pagopar_order_flete' , null);
        WC()->session->set('metodos_envios_flete' , null);
        WC()->session->set('metodos_envios_retiro_local' , null);
                

        if ($_POST['payment_method'] === "pagopar_tarjetas_guardadas") {
            #ini_set('display_errors', 'on');
            #error_reporting(E_ALL);
            
            $db = new DBPagopar(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, "wp_transactions_pagopar");
            $pagoparClass = new Pagopar($order_id, $db, $this->origin);
            
            # actualizamos datos de claves de la clase Pagopar
            $pagoparClass->setKeys($payments['pagopar']->settings['public_key'], $payments['pagopar']->settings['private_key']);
            $pp_result = $pagoparClass->pagoConcurrente($user_id, $formaPagoFinal, $hashOrder);
            return site_url()."/gracias-por-su-compra/?hash=".$hashOrder;
        } else {

                if (is_numeric($formaPagoFinal)) {
                    return sprintf('https://www.pagopar.com/pagos/%s', $hashOrder) . '?forma_pago=' . $formaPagoFinal;
                } else {
                    return sprintf('https://www.pagopar.com/pagos/%s', $hashOrder);
                }
        }

        return null;
    }
    
    private function obtenerIDMetodoPagoFinal() {
        if ($_POST['payment_method']==='pagopar_tarjetas_guardadas'){
            return $_POST['sub_payment_method_pagopar_tarjetas_guardadas'];
        }
        if ($_POST['payment_method']==='pagopar_tarjetas'){
            return $_POST['sub_payment_method_pagopar_tarjetas'];
        }
        if ($_POST['payment_method']==='pagopar_efectivo'){
            return $_POST['sub_payment_method_pagopar_efectivo'];
        }
        if ($_POST['payment_method']==='pagopar_billeteras'){
            return $_POST['sub_payment_method_pagopar_billeteras'];
        }
        if ($_POST['payment_method']==='pagopar_transferencia_bancaria'){
            return 11;
        }                
        if ($_POST['payment_method']==='pagopar_bancard_qr'){
            return 24;
        }      
        if ($_POST['payment_method']==='pagopar_pix'){
            return 25;
        }

        if ($_POST['payment_method']==='pagopar_tarjetas_promocion'){
            return 16;
        }


        if ($_POST['payment_method']==='pagopar_bancard_qr_promocion'){
            return 24;
        }

        if ($_POST['payment_method']==='pagopar_upay'){
            return 26;
        } 
                
    }


    private function runCurl($args, $url) {
        $args = json_encode($args);

        $ch = curl_init();
        $headers = array('Accept: application/json', 'Content-Type: application/json', 'X-Origin: ' . $this->origin);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args);

        $response = curl_exec($ch);
       
        return $response;
    }

    public function generateOrderHash($id = null, $amount = 0, $private_key = null) {
        return sha1($private_key . $id . $amount);
    }

    private function getTotalAmount($items) {
        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += $item['precio_total'];
        }
        return $totalAmount;
    }
    

    public function  pagopar_listar_tarjetas() {
        $this->pedidoPagopar = new Pagopar(0, $this->db, $this->origin);
        $currentUser = wp_get_current_user();
        return $this
                    ->pedidoPagopar
                    ->listCards($currentUser->ID);
    }

    private function pagopar_actualizar_orden_envio($order_id)
    {
        global $woocommerce;
        #global $order_id;
        if (!is_numeric($order_id))
        {
            $user = wp_get_current_user();
            $last_order = wc_get_customer_last_order($user->id);
            $order = wc_get_order($last_order->id);
        }
        else
        {
            $order = wc_get_order($order_id);
            $last_order = $order;
        }

        /* Agregar costo de envio */

        # Calculamos el total de envio por módulo Pagopar
        $jsonEnvio = get_post_meta($last_order->id, 'pagopar_json', true);
        $arrayEnvio = json_decode($jsonEnvio, true);
        if (!is_array($arrayEnvio))
        {
            return '';
        }
        $jsonEnvioSeleccionado = get_post_meta($last_order->id, 'pagopar_json_selected', true);
        $arrayEnvioSeleccionado = json_decode($jsonEnvioSeleccionado, true);

        foreach ($arrayEnvioSeleccionado as $key => $value)
        {
            $totalEnvioSeleccionado = $totalEnvioSeleccionado + $arrayEnvio[$key][$value]['costo'];
        }
        # Fin Calculamos el total de envio por módulo Pagopar


        // Get the customer country code
        $country_code = $order->get_shipping_country();

        // Set the array for tax calculations
        $calculate_tax_for = array(
            'country' => $country_code,
            'state' => '', // Can be set (optional)
            'postcode' => '', // Can be set (optional)
            'city' => '', // Can be set (optional)

        );

        // Optionally, set a total shipping amount
        $new_ship_price = intval($totalEnvioSeleccionado);

        // Get a new instance of the WC_Order_Item_Shipping Object
        $item = new WC_Order_Item_Shipping();

        $item->set_method_title("Envío - Tarifa por Zonas");
        $item->set_method_id("flat_rate:14"); // set an existing Shipping method rate ID
        $item->set_total($new_ship_price); // (optional)
        $item->calculate_taxes($calculate_tax_for);

        $order->add_item($item);

        $order->calculate_totals();
    }

    /*
     * @desc Retorna si un plugin está instalado o no teniendo en cuenta solo su nombre
     */
    /*public function pluginInstalado($nombrePlugin) {
        return false;
        $all_plugins = get_plugins();
        $pos = strrpos(json_encode($all_plugins), $nombrePlugin);
        if($pos !== false) {
            return true;
        }else{
            return false;
        }
    }*/
    //prueba
    public function pluginInstalado($nombrePlugin) {

        $all_plugins = get_plugins();
        $plugin_instalado = false;
        foreach($all_plugins as $pp){
            if($pp['TextDomain']==$nombrePlugin){
                $plugin_instalado = true;
            }
        }
        return $plugin_instalado;
    }
    /**
     * Agrega compatibilidad con el plugin woocommerce-deposits (Author:Webtomizer), modifica el json para enviar a Pagopar con precio parcial
     */
    public function compatibilidadWoocommerceDepositsJsonIniciarTransaccionPagopar($newOrderPagopar, $postID){
        return $newOrderPagopar;    
        # Sobreescribimos el precio de cada item si es que está habilitado el plugin de permite pagos parciales
        $woocommerceDepositsInstalado = $this->pluginInstalado('woocommerce-deposits');
        if ($woocommerceDepositsInstalado===true){
            foreach ($newOrderPagopar['compras_items'] as $key => $value) {
                foreach ($value as $key2 => $value2) {
                    $precioParcial = get_post_meta($postID, '_wc_deposits_deposit_amount', true);
                    $depositsHabilitadoProducto = get_post_meta($postID, '_wc_deposits_enable_deposit', true);
                    
                    if ($depositsHabilitadoProducto==='yes'){
                        $newOrderPagopar['compras_items'][$key]['precio_total'] = $precioParcial;
                        $montoTotal = $montoTotal + $precioParcial;
                        break;
                    }
                    break;
                }
            }
        }
        return $newOrderPagopar;    
    }
    
    
    public function compatibilidadWoocommerceDepositsItemPagopar(){
        global $woocommerce;
        if ($this->pluginInstalado('woocommerce-deposits')===true){
                 $payments = WC()->payment_gateways->payment_gateways();
            
            
                #aqui preguntar por compatibilidad depostis

                $cart_contents = $woocommerce->cart->get_cart();
                foreach ($cart_contents as $key => $value) {
                    #print_r($value['product_id']);
                    # Obtenemos datos del producto
                    $productoDeposito = wc_get_product($value['product_id']); 
                    $precioParcial = get_post_meta($value['product_id'], '_wc_deposits_deposit_amount', true);
                    $depositsHabilitadoProducto = get_post_meta($value['product_id'], '_wc_deposits_enable_deposit', true);
                    
                    if ($depositsHabilitadoProducto==='yes'){

                        
                        $descripcion = 'Pago parcial, entrega de : '.$value['deposit']['deposit'].'. Pendiente de pago '.$value['deposit']['remaining'];

                        /*foreach ($value as $key2 => $value2) {
                            #$descripcion = 'Pago parcial, entrega de : '.$value2['deposit'].'. Pendiente de pago '.$value2['remaining'];
                            $descripcion = 'Pago parcial, entrega de : '.$value2['deposit'].'. Pendiente de pago '.$value2['remaining'];
                        }*/

                         $itemPagopar = array(
                            "nombre"=> $productoDeposito->name,
                            "cantidad"=>1,
                            "precio_total"=> $precioParcial,
                            "ciudad"=> '1',
                            "descripcion"=> $descripcion,
                            "url_imagen"=>urldecode(get_the_post_thumbnail_url($value['product_id'], 'medium')),
                            "peso"=> '',
                            "vendedor_telefono"=>'',
                            "vendedor_direccion"=>'',
                            "vendedor_direccion_referencia"=>'',
                            "vendedor_direccion_coordenadas"=>'',
                            "public_key"=>  $payments['pagopar']->settings['public_key'],
                            "categoria"=>909,
                            "id_producto"=>$value['product_id'],
                            "largo"=> '',
                            "ancho"=> '',
                            "alto"=>  '',
                            "comercio_comision" => $splitBillingHabilitado === true ? $montoComision : 0
                        );
                        
                        
                    }

                    
                    

                }
                return $itemPagopar;

        }
    }
    
    

    // Submit payment and handle response
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $order_items = $order->get_items();

        /* En caso en que se quiera pagar un pedido ya previamente creado, desde Mi cuenta > Pedidos */
        $idOrdenPedidoYaCreado = get_query_var('order-pay');

        if (is_numeric($idOrdenPedidoYaCreado)) {
            $formaPagoFinal = $this->obtenerIDMetodoPagoFinal();
            #$billing_metodo_pago = $_POST['billing_metodo_pago'];
            $adicionalURL = '';
            if (is_numeric($formaPagoFinal)) {
                $adicionalURL = '?forma_pago=' . $formaPagoFinal;
            }

            global $wpdb;
            $ordenYaCreada = $wpdb->get_results($wpdb->prepare("SELECT hash FROM wp_transactions_pagopar WHERE id = %s ORDER BY id DESC LIMIT 1", $idOrdenPedidoYaCreado));
            $urlRedirect = 'https://www.pagopar.com/pagos/' . $ordenYaCreada[0]->hash . $adicionalURL;

            return array(
                'result' => 'success',
                'redirect' => $urlRedirect
            );
        }
        return array(
            'result' => 'success',
            'redirect' => $this->get_transaction_url($order)
        );
    }

    // Validate fields
    public function validate_fields()
    {
        return true;
    }

    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check()
    {
        /* if( $this->enabled == "yes" ) {
          if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
          echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";
          }
          } */
    }

    public function verificar_cuenta()
    {

        //echo "<div class=\"error\"><br><p>". sprintf( __( "<strong>%s</strong> Su sitio no se encuentra en producción en Pagopar.com. Antes de poner público su web, debe hacer el pase a producción. <br /><a href=\"%s\">¿Qué es esto? ¿Cómo paso mi sitio a producción?</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p><br></div>";

    }
    

    

    public function mostrar_logo_pagopar_admin()
    {
        /* echo "<div class=\"notice notice-updated\"><br><p>".'

          <img src="https://cdn.pagopar.com/assets/images/logo-pagopar-400px.png" style="width:144px;" alt="PAGOPAR">
          <br />
          <h2>Datos de su cuenta:</h2><br />
          <strong>Plan de Pagopar:</strong> Avanzado<br />
          <strong>Comisión por venta:</strong> 6,6%<br />
          <strong>Nombre del comercio:</strong> Ushop<br />
          <strong>Entorno:</strong> Desarrollo<br />

          '."</p><br></div>"; */
    }

    function wp_get_current_user() {
        return _wp_get_current_user();
    }
    
  
    
    
    public function payment_fields() {
    global $woocommerce;
    $urlBasePlugin = plugin_dir_url(__FILE__);

    ob_start();
    
    #var_dump($this->datos_adicionales);
    ?><ul class="pagopar_payments" <?php  if (in_array($this->id, array('pagopar_transferencia_bancaria', 'pagopar_bancard_qr'))) :  echo ' style="display:none;" ' ; endif;?>>
                  <?php foreach ($this->datos_adicionales as $key => $value) : ?>
                                <?php #if ($this->id===$value['']) :  ?>
                                <?php #foreach ($value['imagen'] as $key2 => $value2) : ?>
                              
                              <li>
                              <?php  if (in_array($this->id, array('pagopar_transferencia_bancaria', 'pagopar_bancard_qr'))===false) :  ?>
                                  
                                    <label for="sub_payment_method_<?php echo $value['forma_pago'];?>">
                                        <?php if ($value['tipo']==='botonAgregarTarjeta') : ?>                                                
                                           <button type="button" name="" id="pagoparAddCard" style="width: 160px;font-size: 11px;;">Agregar tarjeta</button>
                                       
                                           
                                           
                                        <?php else: ?>
                                        <input id="sub_payment_method_<?php echo $value['forma_pago'];?>" type="radio" class="input-radio" name="sub_payment_method_<?php echo $this->id;?>" value="<?php echo $value['forma_pago'];?>" data-order_button_text="">
                                        <?php echo $value['titulo'];?>
                                       <?php endif ?>
                                    </label>
                                       <?php endif ?>
                                       <?php  if (in_array($this->id, array('pagopar_transferencia_bancaria', 'pagopar_bancard_qr'))) :  ?>
                                    <?php else: ?> 

                                        <span class="methods_group">
                                            <?php foreach ($value['imagen'] as $key2 => $value2) : ?>
                                                <span class="method_item <?php echo $value2['class']; ?>">
                                                    <?php if ($value2['url']!='') : ?>
                                                    <img src="<?php echo $value2['url']; ?>" alt="">
                                                    <?php endif ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if($value2['class']!=''): ?>
                                                    <span class="method_item more_methods"><span class="show_more">+4</span></span>
                                            <?php endif; ?>

                                        </span>
                                    <?php endif; ?> 
                                </li>
                              
                <?php #break; ?>
            <?php #endif; ?>
        <?php endforeach; ?>
                  </ul>
                    <p class="pagopar-copy">Procesado por Pagopar <img src="<?php echo $urlBasePlugin;?>images/medios-pagos/iso-pagopar.png" alt="Pagopar"></p>
                    <?php if ($this->id==='pagopar_tarjetas') : ?> 
                    <script>
                    
                jQuery(".more_methods").click(function(e){
			var hiddenitems = jQuery(this).parent('.methods_group').children('.hidden_method');
			var cant = hiddenitems.length;

			e.preventDefault();
			hiddenitems.toggle();
			jQuery(this).children('.show_more').toggleClass('active');
			if(hiddenitems.is(':visible')) {
				jQuery(this).children('.show_more').text('-' + cant);
			} else {
				jQuery(this).children('.show_more').text('+' + cant);
			}
		});




                    </script>
                    <?php else: ?>
                    
<script>
                    
                jQuery(".payment_methods > li > label, .payment_methods > li > input").click(function(){
                    jQuery(".payment_methods > li .payment_box").hide();
                     jQuery(this).parent().children('.payment_box').show();
		});
		
		jQuery('.pagopar_payments > li').find('input[type=radio]:checked').parents('li').addClass('active');
		jQuery(".pagopar_payments > li > label").click(function(){
			jQuery(this).parents('.pagopar_payments').children('li').removeClass('active');
			jQuery(this).parent().addClass('active');
		});



                    </script>
                    <?php endif; ?><?php
        echo  ob_get_clean();
    }

}?>