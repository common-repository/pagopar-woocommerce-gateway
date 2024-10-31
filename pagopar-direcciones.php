<?php

function crearTablasAdicionales() {
       global $wpdb;
       $tabla = $wpdb->prefix.'pagopar_sincronizacion_log_enviado';

       $wpdb->get_results("CREATE TABLE IF NOT EXISTS ".$tabla."  (
  `log_id` INTEGER NOT NULL AUTO_INCREMENT,
  `json_enviar` longtext CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL COMMENT 'json que se armó par enviar a Pagopar',
  `json_respuesta` longtext CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL COMMENT 'json que respondió Pagopar',
  `log_enviado` tinyint(4) NULL DEFAULT 0 COMMENT '1 es log enviado a Pagopar, 0 es pendiente de envio, 2 es enviado pero retorno error, 3 es cancelado para el envio',
  `fecha` timestamp(0) NOT NULL DEFAULT current_timestamp() COMMENT 'fecha creación del registro',
  `post_id` bigint(20) NOT NULL COMMENT 'ID del post  - producto woocommerce',
  `accion` tinyint(4) NULL DEFAULT NULL COMMENT '1 es crear, 2 es editar, se utiliza para apuntar a la url del endpoint correspondiente',
  PRIMARY KEY (`log_id`))");

       
$tabla = $wpdb->prefix.'pagopar_direcciones';
        $wpdb->get_results("CREATE TABLE IF NOT EXISTS ".$tabla."   (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `direccion` varchar(120) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `ciudad` int(11) NULL DEFAULT NULL,
  `direccion_referencia` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `comentario_pickup` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `aex_pickup_retirar_desde` varchar(10) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `aex_pickup_retirar_hasta` varchar(10) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `fecha_creacion` timestamp(0) NULL DEFAULT current_timestamp(),
  `defecto` tinyint(4) NULL DEFAULT 0,
  `direccion_coordenadas` varchar(80) NULL,
  PRIMARY KEY (`id`) ) ");
       
       $tabla_pagos = $wpdb->prefix.'pagopar_pagos_automaticos';
       $tabla_pagos_detalle = $wpdb->prefix.'pagopar_pagos_automaticos_detalle';
       $wpdb->get_results("CREATE TABLE IF NOT EXISTS ".$tabla_pagos."   (
 `pago_id` INTEGER NOT NULL,
 `order_id` int(11) NOT NULL COMMENT 'id del pedido creado',
 `fecha_creacion` date DEFAULT NULL COMMENT 'fecha de creación del pedido creado',
 `user_id` int(11) NOT NULL COMMENT 'id del cliente del detalle del pedido creado',
 `activo` tinyint(1) NOT NULL COMMENT 'identificador de vigencia del pago automático',
 PRIMARY KEY (`pago_id`)
)");

       $wpdb->get_results("CREATE TABLE IF NOT EXISTS ".$tabla_pagos_detalle."    (
     `pago_detalle_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'identificador del detalle de pago',
     `pago_id` int(11) NOT NULL COMMENT 'identificador del pago cabecera',
     `product_id` int(11) NOT NULL COMMENT 'identificador de producto',
     `pagado` tinyint(1) NOT NULL COMMENT 'bandera de pagado o no pagado',
     `fecha_ultimo_pago` date NULL COMMENT 'fecha del ultimo pago',
     `pagos_a_realizar` int(11) NULL COMMENT 'cantidad de pagos a realizar',
     `pagos_realizados` int(11) NULL COMMENT 'cantidad de pagos realizados',
     `fecha_proximo_pago` date NULL COMMENT 'fecha del proximo pago',
     PRIMARY KEY (`pago_detalle_id`,`pago_id`)
    ) ");
       
       
     /* Si no se agregaron los indices (version anterior del plugin), creamos los indices en pagopar_sincronizacion_log_enviado */  
     $tabla = $wpdb->dbname.'.'.$wpdb->prefix.'pagopar_sincronizacion_log_enviado';
     $resultado =  $wpdb->get_results("SHOW INDEX FROM ".$tabla." ; ");
     
     
     foreach ($resultado as $key => $value) {
         $indices[] = $value->Key_name;
     }
     
     $sqlAgregarIndices = '';

     if(!empty($indices)) {

         if (!in_array('index_log_enviado', $indices)) {
             $sqlAgregarIndices = "ALTER TABLE $tabla ADD INDEX `index_log_enviado`(`log_enviado`) USING BTREE;  ";
             $wpdb->query($sqlAgregarIndices);
         }
         if (!in_array('index_post_id', $indices)) {
             $sqlAgregarIndices = "ALTER TABLE $tabla ADD INDEX `index_post_id`(`post_id`) USING BTREE; ";
             $wpdb->query($sqlAgregarIndices);
         }
         if (!in_array('index_accion', $indices)) {
             $sqlAgregarIndices = 'ALTER TABLE ' . $tabla . ' ADD INDEX `index_accion`(`accion`) USING BTREE; ';
             $wpdb->query($sqlAgregarIndices);
         }
         if (!in_array('index_combinado_log_id_log_enviado', $indices)) {
             $sqlAgregarIndices = 'ALTER TABLE ' . $tabla . '  ADD INDEX `index_combinado_log_id_log_enviado`(`log_id`, `log_enviado`) USING BTREE; ';
             $wpdb->query($sqlAgregarIndices);
         }
         if (!in_array('index_combina_post_id_log_enviado', $indices)) {
             $sqlAgregarIndices = 'ALTER TABLE ' . $tabla . '  ADD INDEX `index_combina_post_id_log_enviado`(`post_id`, `log_enviado`) USING BTREE; ';
             $wpdb->query($sqlAgregarIndices);
         }

     }
     
     
       /* Si no se agregaron los indices (version anterior del plugin), creamos los indices en pagopar_sincronizacion_log_enviado */  
    $tabla = $wpdb->dbname.'.'.$wpdb->prefix.'pagopar_direcciones';
    $resultado =  $wpdb->get_results("desc ".$tabla." ; ");

    foreach ($resultado as $key => $value) {
           $campos[] = $value->Field;
           if ($value->Field==='telefono'){
               $caracteresCampoTelefono = strtolower($value->Type);
           }
    }

    if(!empty($campos)){

        if (!in_array('telefono', $campos)){
            $sqlAgregarCampos = "ALTER TABLE ".$tabla." ADD COLUMN `telefono` varchar(12) NULL AFTER `direccion_coordenadas`; ";
            $wpdb->query( $sqlAgregarCampos );
        }else{
            if ($caracteresCampoTelefono!=='varchar(13)'){
                $sqlAgregarCampos = "ALTER TABLE ".$tabla." MODIFY COLUMN `telefono` varchar(13) DEFAULT NULL AFTER `direccion_coordenadas`; ";
                $wpdb->query( $sqlAgregarCampos );
            }
        }
        
    }
    


  
       
}


function migracionDatosDirecciones(){
    global $wpdb;
  
    
    $tabla = $wpdb->prefix.'posts';
    $tabla2 = $wpdb->prefix.'postmeta';
    
    
    #$payments = WC()->payment_gateways->payment_gateways();
    
     
    /*
     * 
     * falta migrar los datos que estan en ajustes generales y poner ese como defecto
     * 
     * 
    var_dump($payments['pagopar']->settings['public_key']);
    var_dump(get_option('seller_ciudad'));
    var_dump(get_option('seller_addr_ref'));
    var_dump(get_option('seller_coo'));
    die();*/
            
    $migracion_direcciones_por_producto = get_option('migracion_direcciones_por_producto');

    if ($migracion_direcciones_por_producto!=='1'){
       
    $direccionesEncontradas = $wpdb->get_results("
        
        SELECT
GROUP_CONCAT( ID ) AS ID,
product_seller_addr,
product_seller_addr_ref,
product_seller_ciudad,
product_seller_coo,
product_seller_phone
FROM
	(
	SELECT
		p.ID,
		( SELECT pm.meta_value FROM ".$tabla2." pm WHERE pm.meta_key = 'product_seller_addr' AND pm.post_id = p.ID ) AS product_seller_addr,
		( SELECT pm.meta_value FROM ".$tabla2." pm WHERE pm.meta_key = 'product_seller_addr_ref' AND pm.post_id = p.ID ) AS product_seller_addr_ref,
		( SELECT pm.meta_value FROM ".$tabla2." pm WHERE pm.meta_key = 'product_seller_ciudad' AND pm.post_id = p.ID ) AS product_seller_ciudad, 
		( SELECT pm.meta_value FROM ".$tabla2." pm WHERE pm.meta_key = 'product_seller_coo' AND pm.post_id = p.ID ) AS product_seller_coo, 
		( SELECT pm.meta_value FROM ".$tabla2." pm WHERE pm.meta_key = 'product_seller_phone' AND pm.post_id = p.ID ) AS product_seller_phone 
	FROM
		".$tabla." p 
	ORDER BY
		p.ID DESC 
	) AS tabla 
WHERE
	product_seller_addr IS NOT NULL 
	AND product_seller_addr_ref IS NOT NULL 
	AND product_seller_ciudad IS NOT NULL 
GROUP BY
	product_seller_addr,
	product_seller_addr_ref,
	product_seller_ciudad 
        
        ");
      
    
    foreach ($direccionesEncontradas as $key => $value) {
        
        #insertar direccion
        $direccionID = crearEditatDireccion($value->product_seller_addr, $value->product_seller_ciudad, $value->product_seller_addr_ref, $value->product_seller_coo, $value->product_seller_phone);
        
        # Guardamos cual direccion usa las publicaciones
        $IDs = explode(',', $value->ID);
        foreach ($IDs as $key2 => $value2) {
            update_post_meta($value2, 'pagopar_direccion_id_woocommerce', $direccionID->id);
        }
        
    }
    
    update_option('migracion_direcciones_por_producto', '1');
        
    }
         
}

function crearEditatDireccion($direccion, $ciudad, $referencia, $coordenadas, $telefono='') {
    global $wpdb;
    $tabla = $wpdb->prefix.'pagopar_direcciones';
    
    $direccion = trim($direccion);
    $ciudad = trim($ciudad);
    $referencia = trim($referencia);
    $coordenadas = trim($coordenadas);
    $telefono = trim($telefono);
    

    $existeDireccion = $wpdb->get_results($wpdb->prepare("select * from  " . $tabla . " where direccion = %s and ciudad = %s and direccion_referencia = %s and direccion_coordenadas = %s ", $direccion, $ciudad, $referencia, $coordenadas));
    
    # Si no existe direccion, creamos
    if (!is_numeric($existeDireccion->id)){
       $a = $wpdb->get_results($wpdb->prepare("insert into " . $tabla . " (direccion, ciudad, direccion_referencia, direccion_coordenadas, telefono) values (%s, %s, %s, %s, %s)", $direccion, $ciudad, $referencia, $coordenadas, $telefono));
       $existeDireccion = traerDirecciones($wpdb->insert_id);
    }

    return $existeDireccion[0];
}

function traerDirecciones($idDireccion = null) {
    global $wpdb;
    
    if (is_null($idDireccion)){
        $direcciones = $wpdb->get_results("select * from  " . $wpdb->prefix . "pagopar_direcciones");    
    }else{
        $direcciones = $wpdb->get_results($wpdb->prepare("select * from  " . $wpdb->prefix . "pagopar_direcciones where id = %s ", $idDireccion));        
    }

    return $direcciones;
    
}

function traerDireccionDefecto() {
    global $wpdb;
    

    $direcciones = $wpdb->get_results($wpdb->prepare("select * from  " . $wpdb->prefix . "pagopar_direcciones where defecto = %s ", '1'));        

    return $direcciones[0];
    
}

/**
 * Retorna los datos de la direccion de un producto, teniendo en cuenta varios parametros
 */
function obtenerDireccionProducto($idProducto){
    
    $direccionUnicaHabilitada = get_option('direccion_unica_habilitada');
    
    # Si está direccion unica habilitada, entonces retornamos esa direccion

    if ($direccionUnicaHabilitada === '1'){
        $direcciones = traerDireccionDefecto();
    }else{
        $pagopar_direccion_id_woocommerce = get_post_meta($idProducto, 'pagopar_direccion_id_woocommerce', true);
        $direcciones = traerDirecciones($pagopar_direccion_id_woocommerce);
        $direcciones =  $direcciones[0];
        #$direcciones->aex_cometario_pickup = 'asdf';
    }
    
    
    return $direcciones;
    
}

function direccionDefectoUpdate($idDireccion){
    global $wpdb;
    $tabla = $wpdb->prefix.'pagopar_direcciones';   

    $wpdb->get_results($wpdb->prepare("update  " . $tabla . " set defecto = 0 where id <> %s", $idDireccion));    
    $wpdb->get_results($wpdb->prepare("update  " . $tabla . " set defecto = 1 where id = %s", $idDireccion));    
    
    return $idDireccion;
    
}


function direccionUpdate($idDireccion, $direccion, $ciudad, $referencia, $direccion_coordenadas, $telefono){
    global $wpdb;
    $tabla = $wpdb->prefix.'pagopar_direcciones';   
    $wpdb->get_results($wpdb->prepare("update  " . $tabla . " set direccion = %s, ciudad = %s, direccion_referencia = %s, direccion_coordenadas = %s, telefono = %s  where id = %s", $direccion, $ciudad, $referencia, $direccion_coordenadas, $telefono, $idDireccion));        
    return $idDireccion;    
}

function woocommerce_wp_select_multiple( $field ) {
    global $thepostid, $post, $woocommerce;

    $thepostid              = empty( $thepostid ) ? $post->ID : $thepostid;
    $field['class']         = isset( $field['class'] ) ? $field['class'] : 'select short';
    $field['wrapper_class'] = isset( $field['wrapper_class'] ) ? $field['wrapper_class'] : '';
    $field['name']          = isset( $field['name'] ) ? $field['name'] : $field['id'];
    $field['value']         = isset( $field['value'] ) ? $field['value'] : ( get_post_meta( $thepostid, $field['id'], true ) ? get_post_meta( $thepostid, $field['id'], true ) : array() );

    echo '<p class="form-field ' . esc_attr( $field['id'] ) . '_field ' . esc_attr( $field['wrapper_class'] ) . '"><label for="' . esc_attr( $field['id'] ) . '">' . wp_kses_post( $field['label'] ) . '</label><select style="height:150px;" id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $field['name'] ) . '" class="' . esc_attr( $field['class'] ) . '" multiple="multiple">';

    foreach ( $field['options'] as $key => $value ) {

        echo '<option value="' . esc_attr( $key ) . '" ' . ( in_array( $key, $field['value'] ) ? 'selected="selected"' : '' ) . '>' . esc_html( $value ) . '</option>';

    }

    echo '</select> ';

    if ( ! empty( $field['description'] ) ) {

        if ( isset( $field['desc_tip'] ) && false !== $field['desc_tip'] ) {
            echo '<img class="help_tip" data-tip="' . esc_attr( $field['description'] ) . '" src="' . esc_url( WC()->plugin_url() ) . '/assets/images/help.png" height="16" width="16" />';
        } else {
            echo '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
        }

    }
    echo '</p>';
}






/************************************************************************/
add_action('woocommerce_shipping_init', 'request_shipping_quote_method');
function request_shipping_quote_method() {

    if ( ! class_exists( 'WC_Pagopar_Shipping_Courier_Method' ) ) {
        class WC_Pagopar_Shipping_Courier_Method extends WC_Shipping_Method {

            public function __construct( $instance_id = 0) {
                $this->id = 'pagopar_courier';
                $this->instance_id = absint( $instance_id );
                $this->domain = 'pagopar_courier';
                $this->method_title = __( 'Couriers ofrecidos por Pagopar (AEX y MOBI)', $this->domain );
                $this->method_description = __( 'Pagopar va a cotizar el precio del envío en tiempo real de acuerdo a los producto que estén en el carrito', $this->domain );
                $this->supports = array(
                    'shipping-zones',
                    'instance-settings',
                    'instance-settings-modal',
                );
                $this->init();
            }

            ## Load the settings API
            function init() {
                $this->init_form_fields();
                $this->init_settings();
                $this->enabled = $this->get_option( 'enabled', $this->domain );
                $this->title   = $this->get_option( 'title', $this->domain );
                $this->info    = $this->get_option( 'info', $this->domain );
                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
             }

            function init_form_fields() {
                
                $this->instance_form_fields = array(
                    'title' => array(
                        'type'          => 'text',
                        'title'         => __('Título', $this->domain),
                        'default'       => __( 'Couriers ofrecidos por Pagopar (AEX y MOBI)', $this->domain ),
                    )
                    ,
                    'medios_pagos_disponibles' => array(
                        'title' => __('Configuración de servicio de couriers ofrecidos por Pagopar', $this->domain),
                        'type' => 'title',
                        'description' => __('<a href="'.admin_url( 'admin.php?page=envio_function' ).'">Ingresé aquí para configurar las opciones de couriers ofrecidos por Pagopar</a><br />Observación: Los métodos de envíos ofrecidos por Pagopar aparecerán en todas las ciudades. No es necesario agregar este método de envío pora cada zona de envío', $this->domain),
                    )
                    
                    /*,
                    'cost' => array(
                        'type'          => 'text',
                        'title'         => __('Coast', $this->domain),
                        'description'   => __( 'Enter a cost', $this->domain ),
                        'default'       => '',
                    ),*/
                );
                #var_dump($_POST);
                #var_dump($_GET);
            }

            public function calculate_shipping( $packages = array() ) {
                $rate = array(
                    'id'       => $this->id,
                    'label'    => $this->title,
                    'cost'     => '0',
                    'calc_tax' => 'per_order'
                );
//                    'calc_tax' => 'per_item'

                $this->add_rate( $rate );
            }
        }
    }
}

function metodoEnvioPagoarHabilitado(){
    global $wpdb;
    $existeCourier = $wpdb->get_results("SELECT zone_id from " . $wpdb->prefix . "woocommerce_shipping_zone_methods where method_id = 'pagopar_courier' and is_enabled = '1' limit 1");

    if (is_numeric($existeCourier[0]->zone_id)){
        return true;
    }else{
        return false;
    }
    
    
}

add_filter('woocommerce_shipping_methods', 'add_request_shipping_quote');
function add_request_shipping_quote( $methods ) {
    #var_dump($methods);
    $methods['pagopar_courier'] = 'WC_Pagopar_Shipping_Courier_Method';
    return $methods;
}
//woocommerce_shipping_zone_add_method