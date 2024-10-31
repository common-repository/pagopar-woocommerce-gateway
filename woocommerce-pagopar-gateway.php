<?php
/*
  Plugin Name: Pagopar - WooCommerce Gateway
  Plugin URI: https://wordpress.org/plugins/pagopar-woocommerce-gateway
  Description: Extiende WooCommerce añadiendo el Gateway de Pagopar
  Version: 2.7.1
  Author: Pagopar
  Author URI: https://www.pagopar.com/
*/

# No mostramos errores
ini_set('display_errors', 'off');
error_reporting(0);

//$origin_pagopar = 'WOOCOMMERCE 2.6.5';
$GLOBALS['version'] = 'WOOCOMMERCE 2.7.1';

register_activation_hook(__FILE__, 'child_plugin_activate');

add_filter('page_css_class', 'my_css_attributes_filter', 100, 1);
function my_css_attributes_filter($classes) {
  $lista_a_ocultar = wc_get_pages_id_pagopar(array('gracias-por-su-compra', 'confirm-url'));
  foreach ($lista_a_ocultar as $item) {
    foreach ($classes as $class) {
      if ($class === "page-item-".$item) {
        array_push($classes, "hide-page-item");
      }
    }
  }

  return $classes;
}

function wc_get_pages_id_pagopar($hide) {
  global $wpdb;
  $q = "SELECT ID FROM 
$wpdb->posts WHERE post_type = 'page' AND post_name IN (";
  foreach ( $hide as $page ) {
    $q .= $wpdb->prepare('%s,', $page);
  }

  $tohide = $wpdb->get_col( rtrim($q, ',') . ")" );
  return $tohide;
}

/**
    Inicio de codigo para agregar un field más a la barra de navegación de "Mi cuenta"
 **/
function pp_cards_endpoint() {
    add_rewrite_endpoint( 'cards', EP_ROOT | EP_PAGES );
}

add_action( 'init', 'pp_cards_endpoint' );

add_filter( 'woocommerce_states', 'pagopar_custom_woocommerce_states' );

function pagopar_custom_woocommerce_states( $states ) {

        # Si el metodo de envio de Pagopar no está habilitado, retornamos las ciudades por defecto
    if (metodoEnvioPagoarHabilitado()===false){
        return $states;
    }
    
    $ciudades = [];
    $ciudadesResultado = pagoparCurl(
        null,
        null,
        'https://api-plugins.pagopar.com/api/ciudades/1.1/traer',
        false,
        'CIUDADES',
        null,
        true);
    $pp_ciudadesObject = json_decode($ciudadesResultado);
    if (!$pp_ciudadesObject->respuesta) {
        return $states;
    }

    $ciudades_pagopar = $pp_ciudadesObject->resultado;
    $count = count((array)$ciudades_pagopar);
    for ($i=0; $i<$count; $i++) {
        $ciudades["PY".$ciudades_pagopar[$i]->ciudad] = $ciudades_pagopar[$i]->descripcion;
    }
    $states['PY'] = $ciudades;

    return $states;
}

#do_action( 'woocommerce_shipping_methods', $package ); 
/*
add_filter('woocommerce_shipping_methods', 'forzar_calculo_envio', 10, 1);
function forzar_calculo_envio($package) {
    #$package['pagopar_aex'] = 'Pagopar_Gateway';
    #var_dump($package);
    #die('test');
    return $package;
    
}       
*/


/*
// run the action 
do_action( 'woocommerce_load_shipping_methods', $package ); 

// define the woocommerce_load_shipping_methods callback 
function action_woocommerce_load_shipping_methods( $package ) { 
    // make action magic happen here... 
    #var_dump($package);die();
}; 
    */     
// add the action 
//add_action( 'woocommerce_load_shipping_methods', 'action_woocommerce_load_shipping_methods', 10, 1 );



/************************************************************************/
 function obtenerMetodoEnvioSeleccionadoPagopar() {
    global $woocommerce;
    $item = $itemJson;
    $metodo_seleccionado_post = $_POST['shipping_method'][0];
    $metodo_seleccionado = null;
    $monto_delivery = 0;


    # Obtenemos el metodo id de otra forma
    $metodo_seleccionado_post = WC()->session->get('chosen_shipping_methods');
    $metodo_seleccionado_post = $metodo_seleccionado_post[0];

    # Obtenemos la opcion del delivery
    $metodo_seleccionado_opcion = explode(':', $metodo_seleccionado_post);
    $metodo_seleccionado_opcion = $metodo_seleccionado_opcion['1'];

    # se define cual metodo de envio se utilizara, hay que rever esto
    if (trim($metodo_seleccionado_post)!==''){
	    if (strpos($metodo_seleccionado_post, "local_pickup") !== false) {
	        $metodo_seleccionado = "retiro";
	    } else {
	        if (strpos($metodo_seleccionado_post, "flat_rate_aex") !== false) {
	            $metodo_seleccionado = "aex";
	        } elseif (strpos($metodo_seleccionado_post, "flat_rate_mobi") !== false) {
	            $metodo_seleccionado = "mobi";
	        } else {
	            $metodo_seleccionado = "propio";
	        }
	    }
    
    }
    
    

    $resultado['metodo_seleccionado'] = $metodo_seleccionado;
    $resultado['metodo_seleccionado_opcion'] = $metodo_seleccionado_opcion;
    return $resultado;
}

/* Cuando se selecciona desde finalizar compra - Test */
/*function action_woocommerce_checkout_update_order_review($array)
{

    #header("Content-Type: application/json");
    header('Content-Type: text/html; charset=utf-8');

    
}
add_action('woocommerce_checkout_update_order_review', 'action_woocommerce_checkout_update_order_review', 9999, 2);
*/

add_filter('woocommerce_package_rates', 'custom_shipping_costs', 20, 2);
#add_filter('load_shipping_methods', 'custom_shipping_costs', 20, 2);
function custom_shipping_costs($rates, $package) {

  #global $woocommerce;
  #  $woocommerce->session->set( 'reload_checkout ', 'false' ); 

    
    # Si está habilitado el método de envio Pagopar Courier, eliminamos ya que reemplazaremos por los distintos metodos de envios generados dinámicamente
    if (is_object($rates['pagopar_courier'])===true){
       unset($rates['pagopar_courier']);
    }

    $pagopar_metodos_envios_a_mostrar = get_option('pagopar_metodos_envios_a_mostrar');

    # Probamos otro metodo de obtener el estado
    $aexCode = str_replace("PY", "", $package['destination']['state']);

    $pagopar_aex_activo_general = get_option('pagopar_aex_activo_general');
    $pagopar_mobi_activo_general = get_option('pagopar_mobi_activo_general');

    
    $metodo_seleccionado_post = WC()->session->get( 'chosen_shipping_methods' );
    $metodo_seleccionado_post = $metodo_seleccionado_post[0];

    if ((($aexCode !== null) and ($aexCode !== '')) and (metodoEnvioPagoarHabilitado()===true) and ($pagopar_aex_activo_general==='1')) {



        $aex_resultado = calculate_flete($aexCode, $rates);

        # pasamos a objeto
        $aex_resultado = json_decode(json_encode($aex_resultado));
        
        /* version 2.0 de calcular flete*/
        # Para comparar la cantidad de items enviados con la cantidad de medios de envio disponibles
        if (count((array)$aex_resultado->compras_items)){
            $cantidadItems = count((array)$aex_resultado->compras_items);
        }else{
            $cantidadItems = 0;            
        }

        
        # Obtenemos todos los medios de envio de MOBI ()
        foreach ($aex_resultado->compras_items as $key => $value) {

            # Guardamos la cantidad de opciones de delivery ofrecido aex por cada producto
            foreach ($value->opciones_envio->metodo_aex->opciones as $key2 => $value2) {
                $aexDisponiblesCantidad[$value2->id] = $aexDisponiblesCantidad[$value2->id] + 1;
                # Asignamos cuales opciones de aex estan disponibles por producto, para luego validar y mostrar solo las opciones que tengan todos los productos
                //$aexOpcionesDisponibles[$value->id_producto][] = 'AEX:'.$value2->id;
            }
            
            
            if (!is_null($value->opciones_envio->metodo_mobi->opciones)){
                $cantidadMobi = $cantidadMobi + 1;
            }
            
            #var_dump($value->opciones_envio->metodo_mobi->opciones);
            $contador = 0;
                foreach ($value->opciones_envio->metodo_mobi->opciones as $key2 => $value2) {
                    $metodoEntregaPagopar[$contador]['titulo'] = 'Mobi: entrega el '. $value2->fecha_entrega . ' ' . $value2->rango;
                    $metodoEntregaPagopar[$contador]['precio'] = $value2->costo;
                    $metodoEntregaPagopar[$contador]['id'] = $value2->id;
                    $metodoEntregaPagopar[$contador]['rango'] = $value2->rango;
                    $metodoEntregaPagopar[$contador]['courier'] = 'mobi';

                    $contador = $contador + 1;
                }              
        }
        
        # Determinamos el costo de aex segun si tiene un producto o mas en el carrito
        $contadorDireccionesUnicas = 0;

        //echo "Ooooook...<br>";
        //var_dump($aex_resultado->compras_items);die();
        foreach ($aex_resultado->compras_items as $key => $value) {
            
            $identificador = $value->opciones_envio->metodo_aex->id;
            $cantidadProductos = count((array)$aex_resultado->compras_items);

            if ($cantidadProductos>1){
                # Si hay mas de un producto, necesariamente se tuvo que haber seleccionado una opcion de envio para obtener el costo
                    foreach ($value->opciones_envio->metodo_aex->opciones as $key2 => $value2) {
                        # Obtenemos el costo por el ID de la opcion previamente seleccionada

                        if($value2->id===$value->opciones_envio->metodo_aex->id){
                            $envioOpcionCosto[$identificador] = $envioOpcionCosto[$identificador] + $value2->costo;
                        }else{
                            $envioOpcionCosto[$value2->id] = null;
                        }
                    }
            }else{
                foreach ($value->opciones_envio->metodo_aex->opciones as $key2 => $value2) {
                    $envioOpcionCosto[$value2->id] = $value2->costo;
                }
            }
        }
       

        # Recorremos los items para determinar que opciones de aex se mostraran, se mostrara solo si todos los items tienen todas las opciones
        foreach ($aex_resultado->compras_items as $key => $value) {

            $contador = 0;
            # Guardamos la cantidad de opciones de delivery ofrecido aex por cada producto
            foreach ($value->opciones_envio->metodo_aex->opciones as $key2 => $value2) {
                if ($cantidadItems===$aexDisponiblesCantidad[$value2->id]){
                    
                    if (strpos($value2->descripcion, 'Elocker')!==false){
                        $descripcionAux = 'Disponible para retirar ';
                    }else{
                        $descripcionAux = 'Entrega ';
                    }
                    
                    $adicionalTituloVariosProductos = '';
                    if (is_null($envioOpcionCosto[$value2->id])){
                        $adicionalTituloVariosProductos = ' - El precio se mostrará al seleccionar la opción de envio ';                    
                    }


                    $pagopar_metodos_envios_a_mostrar = json_encode(get_option('woocommerce_pagopar_settings'),true);
                    $pagopar_metodos_envios_a_mostrar = json_decode($pagopar_metodos_envios_a_mostrar,true);
                    $pagopar_metodos_envios_a_mostrar = $pagopar_metodos_envios_a_mostrar['pagopar_metodos_envios_a_mostrar'];


                    if($pagopar_metodos_envios_a_mostrar==0){

                        $metodoEntregaPagopar[$contador]['titulo'] = 'AEX: ' . $value2->descripcion . ' - '.$descripcionAux.' en '.$value2->tiempo_entrega .' horas'.$adicionalTituloVariosProductos;
                        $metodoEntregaPagopar[$contador]['precio'] = $envioOpcionCosto[$value2->id];
                        $metodoEntregaPagopar[$contador]['id'] = $value2->id;
                        $metodoEntregaPagopar[$contador]['rango'] = $value2->tiempo_entrega . ' horas';
                        $metodoEntregaPagopar[$contador]['courier'] = 'aex';

                        $contador = $contador + 1;
                    }else{
                        $id_from_pagopar = $value2->id;
                        $id_from_pagopar = explode("-",$id_from_pagopar);
                        $id_from_pagopar = $id_from_pagopar[0];

                        if($pagopar_metodos_envios_a_mostrar==$id_from_pagopar){
                            $metodoEntregaPagopar[$contador]['titulo'] = 'AEX: ' . $value2->descripcion . ' - '.$descripcionAux.' en '.$value2->tiempo_entrega .' horas'.$adicionalTituloVariosProductos;
                            $metodoEntregaPagopar[$contador]['precio'] = $envioOpcionCosto[$value2->id];
                            $metodoEntregaPagopar[$contador]['id'] = $value2->id;
                            $metodoEntregaPagopar[$contador]['rango'] = $value2->tiempo_entrega . ' horas';
                            $metodoEntregaPagopar[$contador]['courier'] = 'aex';

                            $contador = $contador + 1;
                        }

                    }

                    
                }
            }

                $contador_mobi = $contador;
                foreach ($value->opciones_envio->metodo_mobi->opciones as $key3 => $value3) {
                    $metodoEntregaPagopar[$contador_mobi]['titulo'] = 'MOBI - Fecha: '. date("d/m/Y",strtotime($value3->fecha_entrega)) . ' - Horario: '.$value3->rango;
                    $metodoEntregaPagopar[$contador_mobi]['precio'] = $value3->costo;
                    $metodoEntregaPagopar[$contador_mobi]['id'] = $value3->id;
                    $metodoEntregaPagopar[$contador_mobi]['rango'] = 20 . ' horas';#No se utiliza 
                    $metodoEntregaPagopar[$contador_mobi]['courier'] = 'mobi';

                    $contador_mobi = $contador_mobi + 1;
                }
        }    
        
        
         
        if ($pagopar_aex_activo_general==='1'){
            #temp comentado, averiguar si se suman los montos
            #$cost = getAexCost($aex_resultado);
            
            $idDesde = 100;// Dinamizar segun db, averiguar
            foreach ($metodoEntregaPagopar as $key => $value) {
                $pagopar_rate = new WC_Shipping_Rate("flat_rate_".$value['courier'].":".$value['id'].":$idDesde", $value['titulo'], $value['precio'].".00");                
                $rates["flat_rate_".$value['courier'].":".$value['id'].":$idDesde"] = $pagopar_rate;
                #$rates["flat_rate:$idDesde"] = $pagopar_rate;
                $idDesde = $idDesde + 1;# temp, debe venir de la db max(id) + 1
            }
            
        }
        
            
        /* fin versoin 2.0 de calcular flete*/
        
       
        
        # Solo si todos los items tiene MOBI, agregamos MOBI como medio de envio        
        /* #temp comentado temporalmente
         if ($cantidadItems===$cantidadMobi){
            if ($pagopar_mobi_activo_general==='1'){
                $idDesde = 101;// Dinamizar segun db, averiguar
                foreach ($metodoEntregaPagopar as $key => $value) {
                    $pagopar_rate = new WC_Shipping_Rate("flat_rate_mobi:".$value['id'].":$idDesde", $value['titulo'], $value['precio'].".00");
                    #$pagopar_rate = new WC_Shipping_Rate("flat_rate:$idDesde", $value['titulo'], $value['precio'].".00");
                    $rates["flat_rate_mobi:".$value['id'].":$idDesde"] = $pagopar_rate;
                    #$rates["flat_rate:$idDesde"] = $pagopar_rate;
                    $idDesde = $idDesde + 1;# temp, debe venir de la db max(id) + 1
                }
            }
        }*/

        /*if ($cantidadItems===$cantidadMobi){
            if ($pagopar_mobi_activo_general==='1'){
                $idDesde = 301;// Dinamizar segun db, averiguar
                foreach ($metodoEntregaPagopar as $key2 => $value2) {
                    if($value2['courier']=="mobi"){
                        $pagopar_rate = new WC_Shipping_Rate("flat_rate_mobi:".$value2['id'].":$idDesde", 'MOBI', $value2['precio'].".00");
                        #$pagopar_rate = new WC_Shipping_Rate("flat_rate:$idDesde", $value['titulo'], $value['precio'].".00");
                        $rates["flat_rate_mobi:".$value2['id'].":$idDesde"] = $pagopar_rate;
                        #$rates["flat_rate:$idDesde"] = $pagopar_rate;
                        $idDesde = $idDesde + 1;# temp, debe venir de la db max(id) + 1
                    }
                }
            }
        }*/

    }  else {

        WC()->session->set('pagopar_order_flete' , null);
        WC()->session->set('metodos_envios_flete' , null);
        WC()->session->set('metodos_envios_retiro_local' , null);
    }
    

    return $rates;
}

function getAexCost($resultadoFlete){
  foreach ($resultadoFlete->compras_items as $index => $item) {
            //Obtenemos el primer y único elemento en el caso de que sólo haya un método seleccionado
            if (isset($item->opciones_envio)) {
                foreach ($item->opciones_envio as $metodo => $value) {
                    if ($metodo == "metodo_aex" && $value) {
                        return $value->costo;
                    }
                }
            }
        }
}



add_filter( 'woocommerce_account_menu_items', 'pp_new_menu_items' );

/**
 * Insert the new endpoint into the My Account menu.
 *
 * @param array $items
 * @return array
 **/
function pp_new_menu_items( $items ) {
    $pp_commercio = traerDatosComercio();
    $pp_comercioObject = json_decode($pp_commercio);
    if (!$pp_comercioObject->respuesta) {
        return $items;
    }
    $verificar_catastro = $pp_comercioObject->resultado->contrato_firmado;
    if ($verificar_catastro){
        // Remove the logout menu item.
        $logout = $items['customer-logout'];
        unset( $items['customer-logout'] );

        // Insert your custom endpoint.
        $items[ 'cards' ] = __( 'Mis tarjetas', 'pagopar' );

        // Insert back the logout item.
        $items['customer-logout'] = $logout;
    }


    return $items;
}

const endpoint = 'cards';

add_action('woocommerce_account_' . endpoint .  '_endpoint', 'pp_cards_content');
function pp_cards_content() {
    # No mostramos errores
    ini_set('display_errors', 'off');
    error_reporting(0);
    
    #$old_order = wc_get_order(254);
    #$old_order_detail = new WC_Order(254);
    
    $shipping_address = $old_order_detail->data['shipping'];
    #var_dump($shipping_address['state']);
    #die();

    $pp_commercio = traerDatosComercio();
    $pp_comercioObject = json_decode($pp_commercio);
    if (!$pp_comercioObject->respuesta) {
        return '';
    }
    $verificar_catastro = $pp_comercioObject->resultado->contrato_firmado;
    if (!$verificar_catastro){
        return '';
    }
    $nonce = wp_create_nonce("nonce_t");
    $deps = array(
        'jquery',
        'jquery-ui-core'
    );
    $version = '2.6.4';
    $in_footer = true;
    global $post;
    wp_enqueue_script('url', plugins_url('js/catastro-tarjetas.js', __FILE__) , $deps, $version, $in_footer);
    
    wp_enqueue_script('url-modal-js', plugins_url('js/jquery.modal.min.js', __FILE__) , $deps, $version, $in_footer);
    wp_enqueue_style('url-modal-css', plugins_url('css/jquery.modal.min.css', __FILE__));


    wp_localize_script('url', 'urlm', array(
        'ajax_url' => admin_url('admin-ajax.php') ,
        'nonce' => $nonce,
        'postID' => $post->ID
    ));

    wp_register_script('tab-pagopar-js', plugins_url('js/tab_pagopar.js', __FILE__) , array(
        'jquery',
        'jquery-ui-core'
    ) , '1.0');

    wp_enqueue_script( 'bancard-checkout-2.1.0-js', plugins_url( 'js/bancard-checkout-2.1.0.js', __FILE__ ));

    wp_enqueue_script('tab-pagopar-js');

    $response = pp_obtener_lista_tarjetas(false);


    
    $output = "";
    $responseDecode = json_decode($response);
    $list = $responseDecode->resultado;
    
    
    if (boolval($responseDecode->respuesta)) {
        $output .= '
    <style>
    .card {
  box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
  transition: 0.3s;
  width: 100%;
}

.card:hover {
  box-shadow: 0 8px 16px 0 rgba(0,0,0,0.2);
}

.container {
  padding: 2px 16px;
}/* 1. Ensure this sits above everything when visible */
.modal {
    position: fixed;
    z-index: 10000; /* 1 */
    top: 0;
    left: 0;
    visibility: hidden;
    width: 100%;
    height: 100%;
}

.modal.is-visible {
    visibility: visible;
}

.modal-overlay {
  position: fixed;
  z-index: 10;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: hsla(0, 0%, 0%, 0.5);
  visibility: hidden;
  opacity: 0;
  transition: visibility 0s linear 0.3s, opacity 0.3s;
}

.modal.is-visible .modal-overlay {
  opacity: 1;
  visibility: visible;
  transition-delay: 0s;
}

.modal-wrapper {
  position: absolute;
  z-index: 9999;
  top: 6em;
  left: 50%;
  width: 50em;
  margin-left: -16em;
  background-color: #fff;
  box-shadow: 0 0 1.5em hsla(0, 0%, 0%, 0.35);
  height: 60vh;
}

.modal-transition {
  transition: all 0.3s 0.12s;
  transform: translateY(-10%);
  opacity: 0;
}

.modal.is-visible .modal-transition {
  transform: translateY(0);
  opacity: 1;
}

.modal-header,
.modal-content {
  padding: 1em;
}

.modal-header {
  position: relative;
  background-color: #fff;
  box-shadow: 0 1px 2px hsla(0, 0%, 0%, 0.06);
  border-bottom: 1px solid #e8e8e8;
}

.modal-close {
  position: absolute;
  top: 0;
  right: 0;
  padding: 1em;
  color: #aaa;
  background: none;
  border: 0;
}

.modal-close:hover {
  color: #777;
}

.modal-heading {
  font-size: 1.125em;
  margin: 0;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.modal-content > *:first-child {
  margin-top: 0;
}

.modal-content > *:last-child {
  margin-bottom: 0;
}
.loader-1 {
	height: 32px;
	width: 32px;
	margin: auto;
	-webkit-animation: loader-1-1 4.8s linear infinite;
	        animation: loader-1-1 4.8s linear infinite;
}
@-webkit-keyframes loader-1-1 {
	0%   { -webkit-transform: rotate(0deg); }
	100% { -webkit-transform: rotate(360deg); }
}
@keyframes loader-1-1 {
	0%   { transform: rotate(0deg); }
	100% { transform: rotate(360deg); }
}
.loader-1 span {
	display: block;
	position: absolute;
	top: 0; left: 0;
	bottom: 0; right: 0;
	margin: auto;
	height: 32px;
	width: 32px;
	clip: rect(0, 32px, 32px, 16px);
	-webkit-animation: loader-1-2 1.2s linear infinite;
	        animation: loader-1-2 1.2s linear infinite;
}
@-webkit-keyframes loader-1-2 {
	0%   { -webkit-transform: rotate(0deg); }
	100% { -webkit-transform: rotate(220deg); }
}
@keyframes loader-1-2 {
	0%   { transform: rotate(0deg); }
	100% { transform: rotate(220deg); }
}
.loader-1 span::after {
	content: "";
	position: absolute;
	top: 0; left: 0;
	bottom: 0; right: 0;
	margin: auto;
	height: 32px;
	width: 32px;
	color: #00d082;
	clip: rect(0, 32px, 32px, 16px);
	border: 3px solid #000000;
	border-radius: 50%;
	-webkit-animation: loader-1-3 1.2s cubic-bezier(0.770, 0.000, 0.175, 1.000) infinite;
	        animation: loader-1-3 1.2s cubic-bezier(0.770, 0.000, 0.175, 1.000) infinite;
}
@-webkit-keyframes loader-1-3 {
	0%   { -webkit-transform: rotate(-140deg); }
	50%  { -webkit-transform: rotate(-160deg); }
	100% { -webkit-transform: rotate(140deg); }
}
@keyframes loader-1-3 {
	0%   { transform: rotate(-140deg); }
	50%  { transform: rotate(-160deg); }
	100% { transform: rotate(140deg); }
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  grid-gap: 30px;
  align-items: stretch;
}
</style>';
        $output .= '<div class="grid">';
        foreach ($list as $key => $item) {
            $output.= '
            <div class="card" id="'.$item->alias_token.'">
                <img src="'.$item->url_logo.'" alt="Avatar" style="width:100%">
                <div class="container">
                        <h4><b>'.$item->tarjeta_numero.'</b></h4>
                         <button type="button" name="'.$item->alias_token.'" class="pagoparDeleteCard">Eliminar</button>
               </div>
               
            </div>';
        }
        $output .= '</div>';

    }

    $output .= '<button type="button" name="" id="pagoparAddCard">Agregar tarjeta</button>';
    $output .= '<div class="modal">
    <div class="modal-overlay modal-toggle"></div>
    <div class="modal-wrapper modal-transition">
      <div class="modal-header">
        <button class="modal-close modal-toggle"><svg class="icon-close icon" viewBox="0 0 32 32"><use xlink:href="#icon-close"></use></svg></button>
        <h2 class="modal-heading">iFrame vPos</h2>
      </div>
      
      <div class="modal-body">
        <div class="modal-content">
        <div class="loader-1 center"><span></span></div>
           <div style="height: 130px; width: 100%; margin: auto" id="iframe-container"/>
        </div>
      </div>
    </div>
  </div>';
    

    echo $output;
}


function pp_verificar_catastro() {
    $payments = WC()
        ->payment_gateways
        ->payment_gateways();


    $formasPagoJson = $payments['pagopar']->settings['json_forma_pago'];

    $formasPagoArray = json_decode($formasPagoJson, true);

    foreach ($formasPagoArray['resultado'] as $key => $value)
    {
        $formasPagoHabilitadosArray[] = $value['forma_pago'];
    }

    return in_array('14', $formasPagoHabilitadosArray);
}

function pp_obtener_lista_tarjetas($controlPagina = true) {
    # Hacemos la peticion a Pagopar solo si estamos en el checkout o si forzamos el no control de pagina (usado en Mi cuenta > Tarjetas)
    if ($controlPagina===true){
            if (!is_checkout()){        
                return '';
            }
    }
    
    //$respues_agregado = pagopar_agregar_cliente();
    $apiUrl = "https://api-plugins.pagopar.com/api/pago-recurrente/2.0/listar-tarjeta/";
    $response = pagoparCurl(null, null, $apiUrl, false);

    return json_decode(json_encode($response), FALSE);
    return $response;
}


function cards_endpoint_title( $title ) {
    global $wp_query;

    $is_endpoint = isset( $wp_query->query_vars['cards'] );

    if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
        // New page title.
        $title = __( 'Mis tarjetas', 'woocommerce' );

        remove_filter( 'the_title', 'cards_title' );
    }

    return $title;
}

add_filter( 'the_title', 'cards_endpoint_title' );

function pagopar_agregar_cliente()
{
    $apiUrl = "https://api-plugins.pagopar.com/api/pago-recurrente/1.1/agregar-cliente/";
    $response = pagoparCurl(null, null, $apiUrl, true);
    return $response;
}

function pagopar_borrar_tarjeta()
{
    $hash = $_POST['hash_tarjeta'];
    $returnUrl = site_url() . "/mi-cuenta/cards/";
    $apiUrl = "https://api-plugins.pagopar.com/api/pago-recurrente/2.0/eliminar-tarjeta/";
    $response = pagoparCurl($returnUrl, $hash, $apiUrl, false);
    echo $response;
}


function pagopar_agregar_tarjeta()
{
    header('Content-type: application/json');
    # Previamente a agregar tarjeta, debemos agregar el cliente en Pagopar
    $agregarCliente = pagopar_agregar_cliente();
    
    if ($agregarCliente['respuesta']===true){
        $returnUrl = site_url() . "/mi-cuenta/cards/";
        $apiUrl = "https://api-plugins.pagopar.com/api/pago-recurrente/1.1/agregar-tarjeta/";
        $response = pagoparCurl($returnUrl, null, $apiUrl, false);
        echo $response;
        
    }else{
        echo json_encode($agregarCliente);
    }

    exit;
}


function pagopar_catastro_guardar_datos_faltantes()
{
    header('Content-type: application/json');
    # Limpiamos
    $celular = trim($_POST['pagopar_catastro_celular']);
    $celular = '0'.substr($celular, -9);
    
    if (is_user_logged_in()===false){
        $resultado['respuesta'] = false;
        $resultado['resultado'] = 'Usuario no logueado';
        echo json_encode($resultado);
        exit;
    }
    if (strlen($celular)!==10){
        $resultado['respuesta'] = false;
        $resultado['resultado'] = 'Ingrese numero de telefono en formato 0981XXXXXX';
        echo json_encode($resultado);
        exit;

    }
    
    # Actualizamos datos faltantes
    $current_user_id = get_current_user_id();
    @update_user_meta($current_user_id, 'billing_phone', $celular);
    $resultado['respuesta'] = true;
    
    echo json_encode($resultado);

    exit;
}




function pagopar_confirmar_tarjeta()
{
    $returnUrl = site_url() . "/mi-cuenta/cards/";
    $apiUrl = "https://api-plugins.pagopar.com/api/pago-recurrente/1.1/confirmar-tarjeta/";
    $response = pagoparCurl($returnUrl, null, $apiUrl, false);
    echo $response;
}

function pagopar_reversar_pago()
{
    $returnUrl = site_url() . "/mi-cuenta/cards/";
    $apiUrl = "https://api-plugins.pagopar.com/api/pedidos/1.1/reversar";
    $response = pagoparCurl($returnUrl, null, $apiUrl, false, 'PEDIDO-REVERSAR', $_POST["hash_pedido"]);
    $refund = pagopar_wc_refund_order($_POST['id_pedido']);

    echo $response;
}

function pagopar_wc_refund_order( $order_id, $refund_reason = '' ) {

    $order  = wc_get_order($order_id);

    // If it's something else such as a WC_Order_Refund, we don't want that.
    if(!is_a( $order, 'WC_Order')) {
        return new WP_Error( 'wc-order', __( 'Provided ID is not a WC Order', 'yourtextdomain' ) );
    }

    if('refunded' == $order->get_status()) {
        return new WP_Error( 'wc-order', __( 'Order has been already refunded', 'yourtextdomain' ) );
    }


    // Get Items
    $order_items = $order->get_items();

    // Refund Amount
    $refund_amount = 0;

    // Prepare line items which we are refunding
    $line_items = array();

    // Other code will go here
    if ( $order_items ) {
        foreach( $order_items as $item_id => $item ) {


            $item_meta = wc_get_order_item_meta($item_id, '', true);


            $tax_data = $item_meta['_line_tax_data'];

            $refund_tax = 0;

            if( is_array( $tax_data[0] ) ) {

                $refund_tax = array_map( 'wc_format_decimal', $tax_data[0] );

            }

            $refund_amount = wc_format_decimal( $refund_amount ) + wc_format_decimal( $item_meta['_line_total'][0] );

            $line_items[ $item_id ] = array(
                'qty' => $item_meta['_qty'][0],
                'refund_total' => wc_format_decimal( $item_meta['_line_total'][0] ),
                'refund_tax' =>  $refund_tax );

        }
    }

    $refund = wc_create_refund( array(
        'amount'         => $refund_amount,
        'reason'         => $refund_reason,
        'order_id'       => $order_id,
        'line_items'     => $line_items,
        'refund_payment' => true,
        'restock_items' => true
    ));

    return $refund;
}

function traerDatosComercio() {
    

    $expiroCacheDatosComercio =  pagoparCacheCurl('pagopar_datos_comercio_json', 'pagopar_datos_comercio_fecha');

    if ($expiroCacheDatosComercio===false){
        $comercio = json_decode(get_option('pagopar_datos_comercio_json'), true);
    }else{
        # Se hace la petición a Pagopar
        $comercio = pagoparCurl( null, null,'https://api-plugins.pagopar.com/api/comercios/2.0/datos-comercio/',false,'DATOS-COMERCIO', null, true, null, true);
        
        # Guardamos solo si el JSON no contenga un error (como error de token)
        if ($comercio['respuesta']===true){
            update_option('pagopar_datos_comercio_json', json_encode($comercio));
            update_option('pagopar_datos_comercio_fecha', @date('Y-m-d H:i:s'));
        }
    }

    # Retornamos como json ya que se utiliza así en varias partes
    $comercio = json_encode($comercio);

    return $comercio;
}



add_action('admin_head', 'pagopar_wc_refund_button');

function pagopar_wc_refund_button() {

    $deps = array(
        'jquery',
        'jquery-ui-core'
    );
    $nonce = wp_create_nonce("nonce_t");

    $version = '2.6.4';
    $in_footer = true;
    global $post;
    wp_enqueue_script('url', plugins_url('js/catastro-tarjetas.js', __FILE__) , $deps, $version, $in_footer);
    wp_localize_script('url', 'urlm', array(
        'ajax_url' => admin_url('admin-ajax.php') ,
        'nonce' => $nonce,
        'postID' => $post->ID
    ));



    if (!current_user_can('administrator') && !current_user_can('editor')) {
        return;
    }
    if (strpos($_SERVER['REQUEST_URI'], 'post.php?post=') === false) {
        return;
    }

    if (empty($post) || $post->post_type != 'shop_order') {
        return;
    }



    try {
        global $woocommerce;
        $payments = WC()
            ->payment_gateways
            ->payment_gateways();
        $orderId = $_GET['post'];
        $db = new DBPagopar(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, "wp_transactions_pagopar");
        $origin = 'WOOCOMMERCE 2.6.4';
        //Create New Pagopar order
        $pedidoPagopar = new Pagopar($orderId, $db, $origin);
        $pedidoPagopar->publicKey = $payments['pagopar']->settings['public_key'];
        $pedidoPagopar->privateKey = $payments['pagopar']->settings['private_key'];
        $transaction = $pedidoPagopar->getPagoparOrderStatus($orderId);
        $transactionObject = json_decode(trim($transaction), true);
        $resultadoJson = json_encode($transactionObject["resultado"][0]);
        $resultadoObject = json_decode(trim($resultadoJson), true);
        $fechaActual = new DateTime(@date('Y-m-d H:i:s'));
        $dateTimeTransaction = new DateTime($resultadoObject["fecha_maxima_pago"]);
        $diff = $fechaActual->diff($dateTimeTransaction);

        //array 1, 9, 14 ,16
        $array_pagos = [1, 9, 14, 16, 18];
        if(in_array($resultadoObject["forma_pago_identificador"], $array_pagos) &&
            $resultadoObject["pagado"] &&
            $diff->h < 24) {
            ?>
            <script>
                jQuery(function () {
                    var r= jQuery('<button type="button" id="reversePagoparPay" class="button" name="<?php echo $resultadoObject['hash_pedido'] ?>" value="<?php echo $orderId ?>">Reversar pago Pagopar</button>');
                    jQuery(".add-items").append(r);
                });

                jQuery(document).ready( function (e) {
                    var $ = jQuery;
                    if (typeof urlm === 'undefined')
                        return false;

                    jQuery('#reversePagoparPay').click(function (e) {
                        var data = {
                            action: 'pagopar_reversar_pago',
                            nonce: urlm.nonce,
                            hash_pedido: e.target.name,
                            id_pedido: jQuery("#reversePagoparPay").attr("value")
                        };
                        xhr = $.ajax({
                            type: 'POST',
                            url: urlm.ajax_url,
                            data: data,
                            success: function(response) {
                                const json = jQuery.parseJSON(response.substring(0, response.length - 1));
                                if (json.respuesta === true){
                                    location.reload();
                                } else {
                                    alert(json.resultado);
                                }
                            },
                            error: function(code){
                                alert('Hubo un error al revertir el pago. Recargue la página e intente nuevamente');
                            }
                        });
                    });

                });



            </script>
            <?php
        }
    } catch (Exception $e) {

    }
}

function pagoparCurl($returnUrl, $hash, $apiUrl, $isAddClient, $tokenString = 'PAGO-RECURRENTE', $hash_pedido = null, $traerDatos = false, $user = null, $retornarArrayRespuesta = false) {
    if ($user == null) {
      $user = wp_get_current_user();
    }
    $payments = WC()
        ->payment_gateways
        ->payment_gateways();
    $datos['token_publico'] = $payments['pagopar']->settings['public_key'];
    $datos['token'] = sha1($payments['pagopar']->settings['private_key'] . $tokenString);
    $datos['identificador'] = $user->ID;

    if($traerDatos)
        $datos['public_key'] = $payments['pagopar']->settings['public_key'];

    if($hash_pedido != null)
        $datos['hash_pedido'] = $hash_pedido;

    if($returnUrl != null)
        $datos['url'] = $returnUrl;

    if($hash != null)
        $datos['tarjeta'] = $hash;

    if($isAddClient) {
        $current_user_id = get_current_user_id();
        $phone = get_user_meta($current_user_id,'billing_phone',true);
        //phone_number
        $datos['nombre_apellido'] = $user->user_nicename;
        $datos['email'] = $user->user_email;
        $datos['celular'] = $phone;
        

        if (trim($datos['celular'])===''){
            $resultado['respuesta'] = false;
            $resultado['codigo'] = 'NO_TIENE_CELULAR';
            $resultado['resultado'] = 'Debe ingresar su número de celular para catastrar su tarjeta';
            return $resultado;
        }
        
        $retornarArrayRespuesta = true;
        
    }

    //die('fffff');
        

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $apiUrl,
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
    
    if ($retornarArrayRespuesta===true){
        $arrayRespuesta = json_decode($response, true);
        return $arrayRespuesta;
    }else{
        return $response;
    }
    
    
}

/**
 *Fin del codigo par agregar un nuevo item a la barra de navegación
 *
 */

/**
Agregar noticias en el admin
 **/

function pagopar_noticias_admin_notice__error() {

    $pp_commercio = traerDatosComercio();
    $pp_comercioObject = json_decode($pp_commercio);
    if ($pp_comercioObject->respuesta) {
        $verificar_entorno = $pp_comercioObject->resultado->entorno === "Staging";
        if (isset($pp_comercioObject->resultado->pedidos_pendientes)) {
            for ($x = 0; $x < count((array)$pp_comercioObject->resultado->pedidos_pendientes); $x++) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php _e( '<strong>Pagopar</strong> -  Tiene una deuda pendiente de Gs. '.$pp_comercioObject->resultado->pedidos_pendientes[$x]->monto.'' ); ?> <a href="<?php echo $pp_comercioObject->resultado->pedidos_pendientes[$x]->url; ?>">Ver pedido</a> </p>
                </div>
                <?php

            }
        }
        if ($verificar_entorno) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e( '<strong>Pagopar</strong> - Tu entorno de desarrollo es Staging. Al estar terminado tu sitio debes pasar a producción, puedes hacerlo tu mismo siguiendo las <a href="https://soporte.pagopar.com/portal/es/kb/articles/pase-a-producci%C3%B3n-en-woocommerce" target="_blank">instrucciones</a> o <a href="https://soporte.pagopar.com/portal/es/newticket?departmentId=387583000000006907">solicitar a Soporte</a> que te ayudemos con dicha tarea.' ); ?> </p>
            </div>
            <?php
        }
        
         ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e( '<strong>Pagopar</strong> -  Si quiere habilitar los couriers ofrecidos por Pagopar o ya estaba utilizando AEX, debe configurar de nuevo debido a la nueva versión del plugin. ' ); ?> <a target="_blank" href="https://soporte.pagopar.com/portal/es/kb/articles/habilitar-couriers-ofrecidos-por-pagopar">Ver pasos de la configuración</a> </p>
        </div>
        <?php
        

        
    }


}
add_action( 'admin_notices', 'pagopar_noticias_admin_notice__error' );

function pagopar_other_plugins() {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $all_plugins = get_plugins();
    $pos = strrpos(json_encode($all_plugins), "woo-checkout-field-editor-pro");
    if($pos !== false) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e( '<strong>Pagopar</strong> - Al parecer tiene instalado plugins que podrían ocasionar conflictos con el de Pagopar. Te recomendamos leer las <a target="_blank" href="https://soporte.pagopar.com/portal/es/kb/articles/conflicto-con-plugins-de-edici%C3%B3n-de-campos">instrucciones</a> para solucionar dichos conflictos. Si ya realizó esta operación o el equipo de Soporte de Pagopar lo hizo, no tenga en cuenta esta advertencia.' ); ?></p>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'pagopar_other_plugins' );

/**
Fin Agregar noticias en el admin
 **/
function child_plugin_activate()
{
    // Require parent plugin
    if (!is_plugin_active('woocommerce/woocommerce.php') and current_user_can('activate_plugins'))
    {
        // Stop activation redirect and show error
        wp_die('Lo sentimos, pero este plugin requiere <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> para ser activado. <br><a href="' . admin_url('plugins.php') . '">&laquo; Volver a Plugins</a>');
    }
    else
    {
        curlNotificarInstalacionComercio('Instalación');
        create_thanks_pagopar_page();
        create_confirm_url_page();
        
       
    }
}


register_deactivation_hook(__FILE__, 'deactivate_plugin');

function deactivate_plugin()
{

    curlNotificarInstalacionComercio('Desinstalación');
    $page_id = get_option('page_gracias_pagopar');
    wp_delete_post($page_id);
    $page2_id = get_option('page_confirm_url_pagopar');
    wp_delete_post($page2_id);
}

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'pagopar_init', 0);


/*add_action('wp_footer', 'payment_methods_trigger_update_checkout');
function payment_methods_trigger_update_checkout() {
    if( is_checkout() && ! is_wc_endpoint_url() ) :
        ?>
        <script type="text/javascript">
            jQuery(function($){
                $( 'form.checkout' ).on('change', 'input[name="payment_method"]', function() {
                    $(document.body).trigger('update_checkout');
                });
            });
        </script>
    <?php
    endif;
}*/


function pagopar_fees_gastos_administrativos()
{
    if(empty(WC()->cart->get_subtotal()) || WC()->cart->get_subtotal()==0) return;

    #verificar forma de pago
    $selected_payment_method_id = WC()->session->get( 'chosen_payment_method' );

    //echo $selected_payment_method_id;die();
    if($selected_payment_method_id=="pagopar_tarjetas" || $selected_payment_method_id=="pagopar_efectivo"){
        $pagopar_gastos_administrativos = get_option('pagopar_gastos_administrativos');
        $pagopar_gastos_administrativos_text = get_option('pagopar_gastos_administrativos_text');

        if(!empty($pagopar_gastos_administrativos)){
            $monto = (WC()->cart->get_subtotal()*$pagopar_gastos_administrativos)/100;
            WC()->cart->add_fee($pagopar_gastos_administrativos_text,$monto,true);
        }
    }
}


function pagopar_init()
{

   // curlNotificarInstalacionComercio("Instalación");
    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if (!class_exists('WC_Payment_Gateway')) return;

    // If we made it this far, then include our Gateway Class
    include_once ('woocommerce-pagopar.php');
    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_pagopar_gateway');

    function add_pagopar_gateway($methods)
    {
        $methods[] = 'Pagopar_Gateway';
        return $methods;
    }
    
    require_once 'sdk/Pagopar.php';

    register_setting('cats_group', 'cats');
    register_setting('json_group', 'json_flete');
    register_setting('cities_group', 'ciudades');
    register_setting('city_required', 'city_required');
    register_setting('page_thankyou_pagopar', 'page_gracias_pagopar');
    register_setting('page_confirm_url_pagopar', 'page_confirm_url_pagopar');

    /* add_action( 'wp_footer', 'custom_checkbox_checker', 50 ); */
    // PHP functions for Ajax calls
    add_action('wp_enqueue_scripts', 'ajax_pagopar_enqueue_scripts');
    add_action('wp_ajax_pagopar_checkout', 'pagopar_checkout');
    add_action('wp_ajax_nopriv_pagopar_checkout', 'pagopar_checkout');
    add_action('wp_ajax_non_pagopar_checkout', 'non_pagopar_checkout');
    add_action('wp_ajax_nopriv_non_pagopar_checkout', 'non_pagopar_checkout');
    add_action('wp_ajax_change_order_review', 'change_order_review');
    add_action('wp_ajax_nopriv_change_order_review', 'change_order_review');
    add_action('wp_ajax_set_flete', 'set_flete');
    add_action('wp_ajax_nopriv_set_flete', 'set_flete');
    add_action('wp_ajax_pagopar_checkout_change_price', 'pagopar_checkout_change_price');
    add_action('wp_ajax_nopriv_pagopar_checkout_change_price', 'pagopar_checkout_change_price');
    #Fields extra
    add_filter('woocommerce_after_checkout_shipping_form', 'pagopar_shipping_form',10);
    add_filter('the_content', 'pagopar_thankyou');

    // First Register the Tab by hooking into the 'woocommerce_product_data_tabs' filter
    add_filter('woocommerce_product_data_tabs', 'pagopar_product_data_tab');
    add_filter('woocommerce_product_data_panels', 'pagopar_product_data_tab_fields');

    // First Register the Tab by hooking into the 'woocommerce_product_data_tabs' filter
    #add_filter('pagopar_product_data_tab', 'pagopar_split_billing_product_data_tab');
    #add_filter('woocommerce_product_data_panels', 'pagopar_split_billing_product_data_tab_fields');
    //Checkout validations
    //FEES
    //add_action('woocommerce_cart_calculate_fees', 'pagopar_fees_gastos_administrativos');
    add_action( 'woocommerce_cart_calculate_fees', 'pagopar_fees_gastos_administrativos' );
    add_action('wp_ajax_pagopar_add_fees', 'pagopar_add_fees');
    add_action( 'wp_ajax_nopriv_pagopar_add_fees', 'pagopar_add_fees');
    // Enqueue scripts
    add_action('admin_enqueue_scripts', 'show_categories_childs');
    //Admin ajax calls
    add_action('wp_ajax_pagopar_categories', 'pagopar_categories');
    add_action('wp_ajax_nopriv_pagopar_categories', 'pagopar_categories');


    add_action('wp_ajax_pagopar_borrar_tarjeta', 'pagopar_borrar_tarjeta');
    add_action('wp_ajax_nopriv_pagopar_borrar_tarjeta', 'pagopar_borrar_tarjeta');

    add_action('wp_ajax_pagopar_agregar_tarjeta', 'pagopar_agregar_tarjeta');
    add_action('wp_ajax_nopriv_pagopar_agregar_tarjeta', 'pagopar_agregar_tarjeta');


    add_action('wp_ajax_pagopar_catastro_guardar_datos_faltantes', 'pagopar_catastro_guardar_datos_faltantes');
    add_action('wp_ajax_nopriv_pagopar_catastro_guardar_datos_faltantes', 'pagopar_catastro_guardar_datos_faltantes');
    
    
    add_action('wp_ajax_pagopar_confirmar_tarjeta', 'pagopar_confirmar_tarjeta');
    add_action('wp_ajax_nopriv_pagopar_confirmar_tarjeta', 'pagopar_confirmar_tarjeta');


    add_action('wp_ajax_pagopar_reversar_pago', 'pagopar_reversar_pago');
    add_action('wp_ajax_nopriv_pagopar_reversar_pago', 'pagopar_reversar_pago');


    add_action('admin_enqueue_scripts', 'show_categories_childs');

    add_action('admin_menu', 'oaf_create_admin_menu');

    wp_enqueue_style('url', plugins_url('css/pagopar.css', __FILE__));
    
    
    wp_enqueue_style('url-checkout-css', plugins_url('css/checkout.css', __FILE__));        

    
    #add_action('add_styles', 'add_styles');


    crearTablasAdicionales();
    migracionDatosDirecciones();

}


function oaf_create_admin_menu()
{

    add_menu_page('Pagopar', 'Pagopar', 'manage_options',
        'pagopar_create_admin_menu_plugin', 'pagopar_create_admin_menu_function',
        plugins_url('pagopar-woocommerce-gateway/images/isologo-blanco.png'));

    #add_submenu_page ( 'pagopar_create_admin_menu_plugin', 'OAF Options', 'Mi cuenta', 'manage_options', 'oaf_options_submenu1', 'oaf_mi_cuenta_function' );
    add_submenu_page('pagopar_create_admin_menu_plugin', 'Configuración de Pagopar', 'Configuración', 'manage_options', 'pagopar_configuracion', 'oaf_configuracion_function');
    add_submenu_page('pagopar_create_admin_menu_plugin', 'Configuración avanzada de Pagopar', 'Configuración avanzada', 'manage_options', 'pagopar_configuracion_avanzada', 'oaf_configuracion_avanzada_function');

    add_submenu_page('pagopar_create_admin_menu_plugin', 'Promociones Tarjetas de Crédito', 'Promociones Tarjetas de Crédito', 'manage_options', 'promociones_tarjetas_de_credito', 'oaf_promociones_tarjetas_de_credito');

    add_submenu_page('pagopar_create_admin_menu_plugin', 'Módulo de envío', 'Opciones de Envío', 'manage_options', 'envio_function', 'oaf_envio_function');
    
    
    add_submenu_page('pagopar_create_admin_menu_plugin', 'Split Billing en Pagopar', 'Split Billing', 'manage_options', 'pagopar_split_billing', 'oaf_split_billing_function');

    add_submenu_page('pagopar_create_admin_menu_plugin', 'Footer de Pagopar', 'Footer', 'manage_options', 'pagopar_footer', 'oaf_footer_function');

    add_submenu_page('pagopar_create_admin_menu_plugin', 'Chequeo de la configuracion', 'Chequeo', 'manage_options', 'pagopar_chequeo', 'oaf_chequeo_function');

    
}

function pagopar_create_admin_menu_function()
{
    #echo 'Default';
    $page_template = dirname(__FILE__) . '/dashboard-medios-pago.php';
    include $page_template;
}

function oaf_mi_cuenta_function()
{
    $page_template = dirname(__FILE__) . '/dashboard-medios-pago.php';
    include $page_template;
}

function oaf_configuracion_function()
{
    header('Location:' . get_admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=pagopar');
    exit();
}

function oaf_configuracion_avanzada_function()
{
    $page_template = dirname(__FILE__) . '/dashboard-configuracion-avanzada.php';
    include $page_template;
}



function oaf_promociones_tarjetas_de_credito()
{
    $page_template = dirname(__FILE__) . '/dashboard-promociones-tarjetas-de-credito.php';
    include $page_template;
}



function oaf_split_billing_function()
{
    $page_template = dirname(__FILE__) . '/dashboard-split-billing.php';
    include $page_template;
}

function oaf_chequeo_function()
{

    wp_enqueue_style('wp-color-picker');


    $page_template = dirname(__FILE__) . '/dashboard-chequeo.php';
    include $page_template;
}


function oaf_footer_function()
{

    wp_enqueue_style('wp-color-picker');


    $page_template = dirname(__FILE__) . '/dashboard-footer.php';
    include $page_template;
}


function oaf_envio_function()
{

    wp_enqueue_style('wp-color-picker');


    $page_template = dirname(__FILE__) . '/dashboard-envio.php';
    include $page_template;
}

/*
  function add_styles() {

  global $version;

  $version = explode(' ', $origin_pagopar);
  $version = $version[1];
  #$version = rand(999999,999999999);
  #wp_enqueue_style('url', plugins_url('css/pagopar.css', __FILE__), $array(), $version);
  wp_enqueue_style('url', plugins_url('css/pagopar.css', __FILE__));

  } */

function create_thanks_pagopar_page()
{
    $PageGuid = site_url() . "/gracias-por-su-compra";
    $my_post = array(
        'post_title' => 'Datos del pedido',
        'post_type' => 'page',
        'post_name' => 'gracias-por-su-compra',
        'post_content' => '',
        'post_status' => 'publish',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_author' => 1,
        'menu_order' => 0,
        'guid' => $PageGuid
    );

    $PageID = wp_insert_post($my_post, false); // Get Post ID - FALSE to return 0 instead of wp_error.
    update_option('page_gracias_pagopar', $PageID);
}

function create_confirm_url_page()
{
    $PageGuid = site_url() . "/confirm-url";
    $my_post = array(
        'post_title' => 'Confirm',
        'post_type' => 'page',
        'post_name' => 'confirm-url',
        'post_content' => '',
        'post_status' => 'publish',
        'page_template' => 'ConfirmarPagoparUrl',
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_author' => 1,
        'menu_order' => 0,
        'guid' => $PageGuid
    );

    $PageID = wp_insert_post($my_post, false); // Get Post ID - FALSE to return 0 instead of wp_error.
    update_option('page_confirm_url_pagopar', $PageID);
}

add_filter('page_template', 'confirm_url_page_template');

function confirm_url_page_template($page_template)
{
    if (is_page('confirm-url'))
    {
        $page_template = dirname(__FILE__) . '/confirmarpagoparurl.php';
    }
    return $page_template;
}

add_action('pre_get_posts', 'exclude_this_page');

function exclude_this_page($query)
{
    if (!is_admin()) return $query;
    global $pagenow;
    //if ('edit.php' == $pagenow && ( get_query_var('post_type') && 'page' == get_query_var('post_type') ))
    if ('edit.php' == $pagenow && ($_GET['post_type'] && 'page' == $_GET['post_type']))
    {
        $query->set('post__not_in', array(
            get_option('page_confirm_url_pagopar') ,
            get_option('page_gracias_pagopar')
        )); // page id

    }
    return $query;
}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__) , 'pagopar_action_links');

function pagopar_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=pagopar') . '">' . __('Ajustes', 'pagopar') . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge($plugin_links, $links);
}

function formatoFechaLatina($fecha)
{
    $fechaPartes = explode(' ', $fecha);

    #Fecha
    $diaMesAnho = explode('-', $fechaPartes[0]);
    $diaMesAnho = $diaMesAnho[2] . '/' . $diaMesAnho[1] . '/' . $diaMesAnho[0];

    # Hora
    $horaMinuto = explode(':', $fechaPartes[1]);
    $horaMinuto = $horaMinuto[0] . ':' . $horaMinuto[1];

    $resultado['fecha'] = $diaMesAnho;
    $resultado['hora'] = $horaMinuto;
    return $resultado;
}

function formatoEnteroString($monto)
{
    return number_format($monto, 0, ',', '.');
}

function pagopar_thankyou($content)
{
    require_once 'client/client.php';
    if (is_page('gracias-por-su-compra'))
    {
        $pp_client = new Client();
        return $pp_client->getThankYouPage($content);
    }

    return $content;
}

function pagopar_product_data_tab($original_tabs)
{
    $new_tab['pagopar'] = array(
        'label' => __('Pagopar', 'pagopar') ,
        'target' => 'pagopar',
    );
    $insert_at_position = 0; // This can be changed
    $tabs = array_slice($original_tabs, 0, $insert_at_position, true); // First part of original tabs
    $tabs = array_merge($tabs, $new_tab); // Add new
    $tabs = array_merge($tabs, array_slice($original_tabs, $insert_at_position, null, true)); // Glue the second part of original
    return $tabs;
}
/*
function pagopar_split_billing_product_data_tab($original_tabs) {
    $new_tab['pagopar_split_billing'] = array(
        'label' => __('Pagopar - Split Billing', 'pagopar_split_billing'),
        'target' => 'pagopar_split_billing',
    );
    $insert_at_position = 0; // This can be changed
    $tabs = array_slice($original_tabs, 0, $insert_at_position, true); // First part of original tabs
    $tabs = array_merge($tabs, $new_tab); // Add new
    $tabs = array_merge($tabs, array_slice($original_tabs, $insert_at_position, null, true)); // Glue the second part of original
    return $tabs;
}
*/

function pagopar_split_billing_product_data_tab_fields()
{
    ?>
    <div id="pagopar_split_billing_product_data" class="panel woocommerce_options_panel">
        <?php
        $product_id = get_the_ID();

        $payments = WC()
            ->payment_gateways
            ->payment_gateways();
        $comerciosHijosJsonGuardado = json_decode($payments['pagopar']->settings['json_comercio_hijos'], true);

        ?>
        <?php if ($payments['pagopar']->settings['habilitar_split_billing'] === 'yes'): ?>
            <?php if (is_array($comerciosHijosJsonGuardado['resultado'])): ?>
                <select>
                    <?php foreach ($comerciosHijosJsonGuardado['resultado'] as $key => $value): ?>
                        <option value="<?php echo $value['token_publico']; ?>"><?php echo $value['descripcion']; ?></option>
                    <?php
                    endforeach; ?>
                </select>

            <?php
            endif; ?>

        <?php
        endif; ?>

    </div>


    <?php
}

/**
 * Add pagopar categories.
 */
/* function add_wc_pagopar_categories(){ */
function pagopar_product_data_tab_fields()
{

    echo '<div id="pagopar_product_data" class="panel woocommerce_options_panel">';

    $pagopar_cats_id = null;
    $final_cat = null;
    $level_to_save = null;

    $product_id = get_the_ID();

    $final_cat = get_post_meta($product_id, 'pagopar_final_cat', true);
    $level_to_save = get_post_meta($product_id, 'level_to_save_val', true);
    $pagopar_cats_id = explode(",", get_post_meta($product_id, 'pagopar_cats_id', true));

    $payments = WC()
        ->payment_gateways
        ->payment_gateways();
    $consultCatsPagopar = new ConsultPagopar($GLOBALS['version']);
    $consultCatsPagopar->publicKey = $payments['pagopar']->settings['public_key'];
    $consultCatsPagopar->privateKey = $payments['pagopar']->settings['private_key'];
    $cats = $consultCatsPagopar->getProductCategories('array');

    #temp $comerciosHijosJsonGuardado = json_decode($payments['pagopar']->settings['json_comercio_hijos'], true);
    $comerciosHijos = traer_comercios_hijos_asociados($consultCatsPagopar->publicKey, $consultCatsPagopar->privateKey);
    $comerciosHijosJsonGuardado = $comerciosHijos;

    $comerciosHijosArrayGuardado[0] = 'Elegir';
    foreach ($comerciosHijosJsonGuardado['resultado'] as $key => $value)
    {
        $comerciosHijosArrayGuardado[$value['token_publico']] = $value['descripcion'];
    }
    #var_dump($comerciosHijosArrayGuardado);die();
    update_option('cats', $cats);
    // Custom field Type

    ?>

    <?php if ($payments['pagopar']->settings['habilitar_split_billing'] === 'yes'): ?>
    <div class="options_group" >
        <h3 style="padding:0 9px;">Split Billing</h3>


        <?php
        $comercioVendedor = get_post_meta($product_id, 'comercio_hijo_vendedor_producto', true);
        woocommerce_wp_select(array(
            'id' => 'comercio_hijo_vendedor_producto',
            'label' => 'Comercio vendedor',
            'class' => 'wc-enhanced-select',
            'value' => $comercioVendedor,
            'options' => $comerciosHijosArrayGuardado,
        ));

        ?>


    </div>
<?php
endif; ?>


    <?php if (is_numeric($payments['pagopar']->settings['configuracion_avanzada_id_categoria_defecto'])): ?>
    <div class="options_group" >
        <h3 style="padding:0 9px;">Categorías Pagopar</h3>
        <p>Atención, hemos notado que configuraste una categoría genérica por defecto en Ajustes del Plugin, esta funcionalidad debe ser utilizada con cuidado ya que un mal uso de las categorías podría conllevar multas si la categoria es referente a un categoría de Delivery. Use esta opción solo bajo explicación oficial.
        </p>
    </div>
<?php
else: ?>
    <div class="options_group" >
        <h3 style="padding:0 9px;">Categorías Pagopar</h3>
        <p>Las categorías se utilizan para definir el costo del delivery que abonará tu cliente. Para sugerirnos que agreguemos
            una categoría, por favor comunícate con nosotros a <a href="mailto:soporte@pagopar.com">soporte@pagopar.com</a>
        </p>
        <p class="form-field" id="selects_categories_pagopar">
            <label for="product_field_type0"><?php _e('Seleccionar categoría', 'pagopar'); ?></label>
            <?php
            $final_medidas = 0;

            if ($pagopar_cats_id)
            {
                $padres = $cats;
                foreach ($pagopar_cats_id as $key => $cat_id)
                {
                    ?>
                    <select id="product_field_type<?php echo $key; ?>" name="product_field_type<?php echo $key; ?>" class="ajax_chosen_select_products" style="width:30%"
                            multiple="multiple" level="<?php echo $key + 1; ?>" onchange="verificarSelect('product_field_type<?php echo $key; ?>');">
                        <?php
                        foreach ($padres as $padre)
                        {
                            $children = 1;
                            if (empty($padre->hijas)) $children = 0;
                            $sel = '';
                            if ($padre->categoria == $cat_id)
                            {
                                $sel = 'selected';
                                $final_medidas = $padre->medidas;
                            }
                            echo '<option ' . $sel . ' value="' . $padre->categoria . '" productoFisico="' . intval($padre->producto_fisico) . '" medidas="' . $padre->medidas . '" hijos="' . $children . '">' . $padre->descripcion . '</option>';
                        }
                        $pp_padres_array = array_filter($padres, function ($e) use ($cat_id) {
                            return $e->categoria == $cat_id;
                        });
                        $hijas = reset($pp_padres_array);
                        $padres = $hijas->hijas;
                        ?>
                    </select>
                    <?php
                } ?>
                <?php
                // Hidden field
                woocommerce_wp_hidden_input(array(
                    'id' => 'can_save_val',
                    'value' => '1'
                    //echo get_option('level_to_save');

                ));
                ?>
                <?php
            }
            else
            {
                ?>
                <select id="product_field_type0" name="product_field_type0" class="ajax_chosen_select_products" style="width:30%"
                        multiple="multiple" level="1" onchange="verificarSelect('product_field_type0');">
                    <?php
                    foreach ($cats as $padre)
                    {
                        $children = 1;
                        if (empty($padre->hijas)) $children = 0;
                        echo '<option value="' . $padre->categoria . '" medidas="' . $padre->medidas . '" hijos="' . $children . '">' . $padre->descripcion . '</option>';
                    }
                    ?>
                </select>
                <?php
                // Hidden field
                woocommerce_wp_hidden_input(array(
                    'id' => 'can_save_val',
                    'value' => '0'
                    //echo get_option('level_to_save');

                ));
            }
            ?>
            <style type="text/css">
                .spinner{
                    float:none;
                    width:auto;
                    height:auto;
                    padding:0px;
                    background-position:20px 0;
                }
                p.envio_propio{
                    margin: 0px!important;
                    padding: 1px 9px!important;
                }

                .form-field.form-row.variable_weight0_field {display:none !important;}
                .form-field.form-row.variable_weight1_field {display:none !important;}
                .form-field.form-row.variable_weight2_field {display:none !important;}
                .form-field.form-row.variable_weight3_field {display:none !important;}
                .form-field.form-row.variable_weight4_field {display:none !important;}
                .form-field.form-row.variable_weight5_field {display:none !important;}
                .form-field.form-row.variable_weight6_field {display:none !important;}

                .form-field.form-row.dimensions_field {display:none !important;}


            </style>
            <span class="spinner"></span>
            <?php
            // Hidden field
            woocommerce_wp_hidden_input(array(
                'id' => 'level_to_save_val',
                'value' => $level_to_save
                //echo get_option('level_to_save');

            ));
            // Hidden field
            woocommerce_wp_hidden_input(array(
                'id' => 'pagopar_final_cat',
                'value' => $final_cat
            ));
            // Hidden field
            woocommerce_wp_hidden_input(array(
                'id' => 'pagopar_cats_id',
                'value' => $cats
            ));
            ?>
        </p>
        <?php if ($final_medidas)
        { ?>
            <style type="text/css">

                /* #woocommerce-product-data .type_box label[for=_virtual] {
                     display: none!important;
                 }

                 #woocommerce-product-data .type_box label[for=_downloadable] {
                     display: none!important;
                 }
 */



                #pagopar_product_data .form-field.dimensions_field{ display: none; }
                #pagopar_product_data .form-field.product_weight_field{ display: none; }
            </style>
            <?php
        }
        else
        {
            ?>
            <style type="text/css">

                /*#woocommerce-product-data .type_box label[for=_virtual] {
                    display: none!important;
                }

                #woocommerce-product-data .type_box label[for=_downloadable] {
                    display: none!important;
                }
*/
                #pagopar_product_data .form-field.dimensions_field{ display: none; }
                #pagopar_product_data .form-field.product_weight_field+.form-field.dimensions_field{ display: block; }
            </style>
            <?php
        }
        woocommerce_wp_text_input(array(
            'id' => 'product_weight',
            'label' => 'Peso (kg)',
            'value' => get_post_meta($product_id, 'product_weight', true) ,
            'default' => 0
        ));
        ?>
        <p class="form-field dimensions_field">
            <label for="pagopar_largo">Dimensiones (cm)</label>
            <span class="wrap">
                <input id="pagopar_largo" placeholder="Longitud" class="input-text wc_input_decimal" size="6" type="text"
                       name="pagopar_largo" value="<?php echo get_post_meta($product_id, 'pagopar_largo', true); ?>">
                <input placeholder="Anchura" class="input-text wc_input_decimal" size="6" type="text"
                       name="pagopar_ancho" value="<?php echo get_post_meta($product_id, 'pagopar_ancho', true); ?>">
                <input placeholder="Altura" class="input-text wc_input_decimal last" size="6" type="text"
                       name="pagopar_alto" value="<?php echo get_post_meta($product_id, 'pagopar_alto', true); ?>">
            </span>
        </p>
    </div>
<?php
endif; ?>
    <?php
    $sellphone = get_post_meta($product_id, 'product_seller_phone', true);
    $selladdr = get_post_meta($product_id, 'product_seller_addr', true);
    $sellciudad = get_post_meta($product_id, 'product_seller_ciudad', true);
    $selladdrref = get_post_meta($product_id, 'product_seller_addr_ref', true);
    $sellcoo = get_post_meta($product_id, 'product_seller_coo', true);
    $enabled = get_post_meta($product_id, 'product_enabled_retiro', true);
    $obs = get_post_meta($product_id, 'product_sucursal_obs', true);

    # Esto se usa en lugar de los datos separados de direcion por producto
    $pagopar_direccion_id_woocommerce = get_post_meta($product_id, 'pagopar_direccion_id_woocommerce', true);
    
    # campos nuevos aex
    $pagopar_envio_aex_comentario_pick_up = get_post_meta($product_id, 'pagopar_envio_aex_comentario_pick_up', true);
    $pagopar_envio_aex_activo = get_post_meta($product_id, 'pagopar_envio_aex_activo', true);
    $pagopar_envio_aex_id = get_post_meta($product_id, 'pagopar_envio_aex_id', true);
    $pagopar_envio_aex_direccion = get_post_meta($product_id, 'pagopar_envio_aex_direccion', true);
    $pagopar_envio_aex_pickup_horario_inicio = get_post_meta($product_id, 'pagopar_envio_aex_pickup_horario_inicio', true);
    $pagopar_envio_aex_pickup_horario_fin = get_post_meta($product_id, 'pagopar_envio_aex_pickup_horario_fin', true);
    
    if (trim($pagopar_envio_aex_pickup_horario_inicio)===''){
        $pagopar_envio_aex_pickup_horario_inicio = get_option('pagopar_envio_aex_pickup_horario_inicio');        
    }
    if (trim($pagopar_envio_aex_pickup_horario_fin)===''){
        $pagopar_envio_aex_pickup_horario_fin = get_option('pagopar_envio_aex_pickup_horario_fin');
    }
    
  
    # Si se usa una sola direccion global
    $direccion_unica_habilitada = get_option('direccion_unica_habilitada'); 
    
    $citiesConsultPagopar = new ConsultPagopar($GLOBALS['version']);
    $citiesConsultPagopar->publicKey = $payments['pagopar']->settings['public_key'];
    $citiesConsultPagopar->privateKey = $payments['pagopar']->settings['private_key'];




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
        $cities_wc_format = $payments['pagopar']->settings['seller_ciudad'];
    }

    #var_dump($cities_wc_format);die();
    $city_selected = ($sellciudad) ? $sellciudad : $payments['pagopar']->settings['seller_ciudad'];

    echo '<div class="options_group">';
    echo '<h3 style="padding:0 9px;">¿Dónde se encuentra el producto?</h3>';
    
    if (!is_numeric($direccion_unica_habilitada)) {
        echo '<p>El courier tercerizado como AEX pasará a buscar el producto utilizando los siguientes datos</p>';  
    }
    
    $direcciones = traerDirecciones();
    
    
    # Formateamos para usar con woocommerce_wp_select
    foreach ($direcciones as $key => $value) {
        $direccionesArray[$value->id] = $value->direccion;
    }
  
    
    if (is_numeric($direccion_unica_habilitada)) {
        echo '<p>TIene habilitada la utilización de una única dirección para todos los productos, si este producto se encuentra en otra dirección, debe destildar dicha opción en Pagopar > Opciones de envío</p>';
    }else{
    
        woocommerce_wp_select(array(
            'id' => 'pagopar_direccion_id_woocommerce',
            'label' => 'Direcciones',
            'class' => 'wc-enhanced-select',
            'value' => $pagopar_direccion_id_woocommerce,
            'options' => $direccionesArray,
        ));

    }
    
    
    echo '<h3 style="padding:0 9px;">Opciones del Courier AEX</h3>';

     # nuevos campos aex
  woocommerce_wp_text_input(array(
        'id' => 'pagopar_envio_aex_comentario_pick_up',
        'label' => 'Comentarios sobre el pickup',
        'value' => ($pagopar_envio_aex_comentario_pick_up) ? $pagopar_envio_aex_comentario_pick_up : $payments['pagopar']->settings['pagopar_envio_aex_comentario_pick_up'],
    ));   
  
    $horarios = array('08:00:00'=>'08:00', '09:00:00'=>'09:00', '10:00:00'=>'10:00', '11:00:00'=>'11:00', '12:00:00'=>'12:00', '13:00:00'=>'13:00', '14:00:00'=>'14:00', '15:00:00'=>'15:00', '16:00:00'=>'16:00', '17:00:00'=>'17:00', '18:00:00'=>'18:00');
    
    
    if (trim($pagopar_envio_aex_pickup_horario_inicio)===''){
        $pagopar_envio_aex_pickup_horario_inicio = '08:00:00';
    }
    woocommerce_wp_select(array(
        'id' => 'pagopar_envio_aex_pickup_horario_inicio',
        'label' => 'Buscar el producto desde las ',
        'class' => 'wc-enhanced-select',
        'value' => $pagopar_envio_aex_pickup_horario_inicio,
        'options' => $horarios,
    ));
      
    if (trim($pagopar_envio_aex_pickup_horario_fin)===''){
        $pagopar_envio_aex_pickup_horario_fin = '18:00:00';
    }
    woocommerce_wp_select(array(
        'id' => 'pagopar_envio_aex_pickup_horario_fin',
        'label' => 'Buscar el producto hasta las ',
        'class' => 'wc-enhanced-select',
        'value' => $pagopar_envio_aex_pickup_horario_fin,
        'options' => $horarios,
    ));
  
    /*
    echo '<h3 style="padding:0 9px;">Opciones del Courier MOBI</h3>';
    
    
    
    woocommerce_wp_select_multiple( array(
    'id' => 'newoptions',
    'name' => 'newoptions[]',
    'class' => 'newoptions',
    'label' => __('Días disponibles para entregar el producto', 'woocommerce'),
    'options' => array(
        '1' => 'Lunes',
        '2' => 'Martes',
        '3' => 'Miércoles',
        '4' => 'Jueves',
        '5' => 'Viernes',
        '6' => 'Sábado',
        '7' => 'Domingo',
    ))
);
    
    ## cambiar nombre variables
    

    if (trim($pagopar_envio_mobi_pickup_horario_inicio)===''){
        $pagopar_envio_mobi_pickup_horario_inicio = '08:00:00';
    }
    woocommerce_wp_select(array(
        'id' => 'pagopar_envio_mobi_pickup_horario_inicio',
        'label' => 'Buscar el producto desde las ',
        'class' => 'wc-enhanced-select',
        'value' => $pagopar_envio_mobi_pickup_horario_inicio,
        'options' => $horarios,
    ));
      
    if (trim($pagopar_envio_mobi_pickup_horario_fin)===''){
        $pagopar_envio_mobi_pickup_horario_fin = '18:00:00';
    }
    woocommerce_wp_select(array(
        'id' => 'pagopar_envio_mobi_pickup_horario_fin',
        'label' => 'Buscar el producto hasta las ',
        'class' => 'wc-enhanced-select',
        'value' => $pagopar_envio_mobi_pickup_horario_fin,
        'options' => $horarios,
    ));    
    */
    
  
    /*
    $pagopar_envio_aex_activo = get_post_meta($product_id, 'pagopar_envio_aex_activo', true);
    $pagopar_envio_aex_id = get_post_meta($product_id, 'pagopar_envio_aex_id', true);
    $pagopar_envio_aex_direccion = get_post_meta($product_id, 'pagopar_envio_aex_direccion', true);
       */ 
    
    echo '</div>';
    echo '</div>';
    ?>
    <?php if (false): ?>
    <div class="options_group">
        <h3 style="padding:0 9px;">Envío Propio</h3>
        <h4 style="padding:0 9px;">Si tu comercio no tiene habilitada esta opción, no es necesario que complete estos datos.
            Para saber si tu comercio tiene habilitado el envío propio, comunícate con nosotros a <a href="mailto:soporte@pagopar.com">soporte@pagopar.com</a></h4>
        <h4 style="padding:0 9px;">¿Posee envío propio para este producto?</h4>
        <p>Si no realiza envío en una o todas las ciudades, la empresa AEX se encargará de recoger su
            producto desde la dirección cargada y llevarlo al cliente.</p>

        <div class="row">
            <div class="col-lg-2">
                <p class="form-field">
                    <label for="desdePropio">DESDE</label>
                    <span id="desdePropio"><?php echo $cities_wc_format[$city_selected]; ?></span>
                </p>
            </div>
            <div class="col-lg-3">
                <?php
                woocommerce_wp_select(array(
                    'id' => 'product_direccion_ciudad_todas',
                    'label' => 'HASTA',
                    'class' => 'wc-enhanced-select',
                    'value' => get_post_meta($product_id, 'product_direccion_ciudad_todas', true) ,
                    'options' => $cities_wc_format,
                ));
                ?>
            </div>
            <div class="col-lg-2">
                <?php
                woocommerce_wp_text_input(array(
                    'id' => 'product_monto_envio',
                    'label' => 'MONTO',
                    'value' => get_post_meta($product_id, 'product_monto_envio', true) ,
                    'placeholder' => 'Ej. 15000',
                ));
                ?>
            </div>
            <div class="col-lg-2">
                <?php
                woocommerce_wp_text_input(array(
                    'id' => 'product_horas',
                    'label' => 'HORAS',
                    'value' => get_post_meta($product_id, 'product_horas', true) ,
                    'type' => 'decimal',
                    'placeholder' => 24,
                ));
                ?>
            </div>
            <div class="col-lg-2">
                <p class="form-field">
                    <label for="agregarSoporteEnvio">ACCIONES</label>
                    <button id="agregarSoporteEnvio" javascript="void(0)" type="button"
                            class="button button-primary button-large">
                        Agregar soporte de envío
                    </button>
                    <span class="spinner" id="spinner-envio-btn"></span>
                </p>
            </div>
            <div id="envios_propios_seleccionados" class="col-lg-12">
                <?php
                $envio_propio = get_post_meta($product_id, 'product_envios_propios', true);
                $propios = json_decode($envio_propio);
                if (!empty($envio_propio))
                {
                    foreach ($propios as $propio)
                    {
                        $d_id = (int)$propio[0];
                        $d = $cities_wc_format[$d_id]; //destinoID
                        $c = $propio[1]; //costo
                        $t = $propio[2]; //tiempo
                        $identi = $d . '_' . $c . '_' . $t;
                        echo "<p class='envio_propio' id='" . $identi . "' o='" . $city_selected . "' d='" . $d_id . "' c='" . $c . "' t='" . $t . "'>";
                        echo "Enviar tu producto de <span class='origin_envio_propio'>" . $cities_wc_format[$city_selected] . "</span> ";
                        echo "a " . $d . " le costará al cliente " . $c . " Gs. adicionales en " . $t . " Hs. ";
                        echo "<a class='delete_envio_propio' style='color:#a00;padding-left:7px;cursor:pointer;text-decoration:underline;'>";
                        echo "Eliminar</a>";
                        echo "</p>";
                    }
                }
                ?>
            </div>
            <?php
            // Hidden field
            woocommerce_wp_hidden_input(array(
                'id' => 'envios_propios_array',
                'value' => $envio_propio
            ));
            ?>
        </div>
    </div>
    <?php endif; ?>
    <?php  ?>
        
    <?php
    if (false):
    echo '<div class="options_group">';
    echo '<h3 style="padding:0 9px;">Retiro de Sucursal</h3>';
    woocommerce_wp_checkbox(array(
        'id' => 'product_enabled_retiro',
        'label' => 'Habilitar el retiro de sucursal para este producto'
    ));
    woocommerce_wp_text_input(array(
        'id' => 'product_sucursal_obs',
        'label' => 'Observaciones',
        'value' => ($obs) ? $obs : $payments['pagopar']->settings['sucursal_obs'],
    ));
    echo '</div>';
    echo '<div class="options_group">';
    echo '<h3 style="padding:0 9px;">Subscripción</h3>';
    woocommerce_wp_checkbox(array(
        'id' => 'product_subscription_enabled',
        'label' => 'Habilitar la subscripción para este producto'
    ));
    woocommerce_wp_select(array(
        'id' => 'product_subscription_date',
        'label' => 'Cobro',
        'class' => 'wc-enhanced-select',
        'value' => get_post_meta($product_id, 'product_subscription_date', true),
        'options' =>  array(
      '30' => __( 'Mensual', 'pagopar' ),
      '7' => __( 'Semanal', 'pagopar' )
    ),
    ));
    woocommerce_wp_text_input(array(
        'id' => 'product_suscription_quantity',
        'label' => 'Cantidad de pagos',
        'value' => get_post_meta($product_id, 'product_suscription_quantity', true),
    ));
    echo '</div>';
    echo '</div>';
    endif; 

}

function show_categories_childs($hook)
{
    if ($hook != 'edit.php' && $hook != 'post.php' && $hook != 'post-new.php') return;

    global $post;
    $nonce = wp_create_nonce("nonce_t");
    $deps = array(
        'jquery',
        'jquery-ui-core'
    );
    $version = '2.6.4';
    $in_footer = true;
    wp_enqueue_script('url', plugins_url('js/admin_footer.js', __FILE__) , $deps, $version, $in_footer);
    wp_localize_script('url', 'urlm', array(
        'ajax_url' => admin_url('admin-ajax.php') ,
        'nonce' => $nonce,
        'postID' => $post->ID
    ));

    wp_register_script('tab-pagopar-js', plugins_url('js/tab_pagopar.js', __FILE__) , array(
        'jquery',
        'jquery-ui-core'
    ) , '1.0');
    wp_enqueue_script('tab-pagopar-js');


}

// Save categories
add_action('woocommerce_process_product_meta', 'pagopar_on_product_save');

function pagopar_on_product_save($post_id)
{
    // do something with this product
    if ($_POST['can_save_val'])
    {
        $cats_to_save = [];
        $cat_level = $_POST['level_to_save_val'];
        for ($i = 0;$i <= $cat_level;$i++)
        {
            if (is_array($_POST['product_field_type' . $i]))
            {
                $cats_to_save[$i] = $_POST['product_field_type' . $i][0];
            }
            else
            {
                $cats_to_save[$i] = $_POST['product_field_type' . $i];
            }
        }
        $cats_string = implode(",", $cats_to_save);

        update_post_meta($post_id, 'pagopar_cats_id', $cats_string);

        $woocommerce_final_field = $_POST['pagopar_final_cat'];
        if (!empty($woocommerce_final_field))
        {
            update_post_meta($post_id, 'pagopar_final_cat', end($cats_to_save));
        }

        $woocommerce_level_field = $_POST['level_to_save_val'];
        if (!empty($woocommerce_level_field))
        {
            update_post_meta($post_id, 'level_to_save_val', esc_attr($woocommerce_level_field));
        }
    }
    else
    {
        echo "<script type='text/javascript'>alert('La categoría seleccionada no debe tener hijos. Por favor, seleccione una categoría válida');</script>";
        update_post_meta($post_id, 'pagopar_cats_id', null);
        update_post_meta($post_id, 'pagopar_final_cat', null);
        update_post_meta($post_id, 'level_to_save_val', null);
    }

    update_post_meta($post_id, 'product_weight', esc_attr($_POST['product_weight']));
    update_post_meta($post_id, 'pagopar_largo', esc_attr($_POST['pagopar_largo']));
    update_post_meta($post_id, 'pagopar_ancho', esc_attr($_POST['pagopar_ancho']));
    update_post_meta($post_id, 'pagopar_alto', esc_attr($_POST['pagopar_alto']));

    $pagopar_direccion_id_woocommerce = $_POST['pagopar_direccion_id_woocommerce'];
    if (!empty($pagopar_direccion_id_woocommerce))
    {
        update_post_meta($post_id, 'pagopar_direccion_id_woocommerce', esc_attr($pagopar_direccion_id_woocommerce));
    }    
            
            
            
    /*$wc_seller_phone_field = $_POST['product_seller_phone'];
    if (!empty($wc_seller_phone_field))
    {
        update_post_meta($post_id, 'product_seller_phone', esc_attr($wc_seller_phone_field));
    }
    $wc_seller_addr_field = $_POST['product_seller_addr'];
    if (!empty($wc_seller_addr_field))
    {
        update_post_meta($post_id, 'product_seller_addr', esc_attr($wc_seller_addr_field));
    }
    $wc_seller_addr_ref_field = $_POST['product_seller_addr_ref'];
    if (!empty($wc_seller_addr_ref_field))
    {
        update_post_meta($post_id, 'product_seller_addr_ref', esc_attr($wc_seller_addr_ref_field));
    }
    $wc_seller_coo_field = $_POST['product_seller_addr_ref'];
    if (!empty($wc_seller_coo_field))
    {
        update_post_meta($post_id, 'product_seller_addr_ref', esc_attr($wc_seller_coo_field));
    }

    $wc_seller_coo_field = $_POST['product_seller_coo'];
    if (!empty($wc_seller_coo_field))
    {
        update_post_meta($post_id, 'product_seller_coo', esc_attr($wc_seller_coo_field));
    }

    $wc_seller_ciudad_field = $_POST['product_seller_ciudad'];
    if (!empty($wc_seller_ciudad_field))
    {
        update_post_meta($post_id, 'product_seller_ciudad', esc_attr($wc_seller_ciudad_field));
    }*/
    
    $wc_enabled_retiro_field = (empty($_POST['product_enabled_retiro']) or $_POST['product_enabled_retiro'] == "no") ? "no" : "yes";
    if (!empty($wc_enabled_retiro_field))
    {
        update_post_meta($post_id, 'product_enabled_retiro', esc_attr($wc_enabled_retiro_field));
    }
    $wc_sucursal_obs_field = $_POST['product_sucursal_obs'];
    if (!empty($wc_sucursal_obs_field))
    {
        update_post_meta($post_id, 'product_sucursal_obs', esc_attr($wc_sucursal_obs_field));
    }
    $wc_envios_propios_array = $_POST['envios_propios_array'];
    if (!empty($wc_envios_propios_array))
    {
        update_post_meta($post_id, 'product_envios_propios', $wc_envios_propios_array);
    }

    $wc_comercio_hijo_vendedor_producto = $_POST['comercio_hijo_vendedor_producto'];
    if (!empty($wc_comercio_hijo_vendedor_producto))
    {
        update_post_meta($post_id, 'comercio_hijo_vendedor_producto', esc_attr($wc_comercio_hijo_vendedor_producto));
    }

    $wc_enabled_subscription_field = (empty($_POST['product_subscription_enabled']) or $_POST['product_subscription_enabled'] == "no") ? "no" : "yes";
    if (!empty($wc_enabled_subscription_field))
    {
        update_post_meta($post_id, 'product_subscription_enabled', esc_attr($wc_enabled_subscription_field));
    }
    $wc_product_subscription_date_field = $_POST['product_subscription_date'];
    if (!empty($wc_product_subscription_date_field))
    {
        update_post_meta($post_id, 'product_subscription_date', esc_attr($wc_product_subscription_date_field));
    }
    $wc_product_suscription_quantity = $_POST['product_suscription_quantity'];
    if (!empty($wc_product_suscription_quantity))
    {
        update_post_meta($post_id, 'product_suscription_quantity', esc_attr($wc_product_suscription_quantity));
    }

}

function pagopar_categories()
{
    $cats = get_option('cats');
    $array = array_filter($cats, function ($e) {
        return $e->categoria == $_POST['padres'][0];
    });


    $hijas = reset($array);
    unset($_POST['padres'][0]);
    foreach ($_POST['padres'] as $cat_id)
    {
        $array_hijas = array_filter($hijas->hijas, function ($e) use ($cat_id) {
            return $e->categoria == $cat_id;
        });
        $hijas = reset($array_hijas);
    }
    $output = '';
    foreach ($hijas->hijas as $hijo)
    {
        $children = 1;
        if (empty($hijo->hijas)) $children = 0;
        $output .= '<option value="' . $hijo->categoria . '" productofisico="' . intval($hijo->producto_fisico) . '" medidas="' . $hijo->medidas . '" hijos="' . $children . '">' . $hijo->descripcion . '</option>';
    }
    echo $output;
}


add_filter('woocommerce_checkout_fields', 'make_fields_non_required', 9999, 1);
#add_filter('woocommerce_checkout_fields', 'make_fields_non_required', 10, 1);



add_action('wp_footer', 'mostrar_footer_pagopar', 100);

function mostrar_footer_pagopar()
{
    
    echo '<script type="text/javascript">
 var $ = jQuery;       

$( ".more_methods" ).on( "click", function(event) {
 alert( "The mouse cursor is at (" +
      event.pageX + ", " + event.pageY +
      ")" );
      
});

function notify() {
  alert( "clicked" );
}
$( ".more_methods" ).on( "click", notify );


jQuery( document ).ready(function() {
/*alert();*/
		jQuery(".payment_methods > li > label, .payment_methods > li > input").click(function(){
			jQuery(".payment_methods > li .payment_box").hide();
			jQuery(this).parent().children(\'.payment_box\').show();
		});
		jQuery(".more_methods").click(function(e){
			var hiddenitems = $(this).parent(\'.methods_group\').children(\'.hidden_method\');
			var cant = hiddenitems.length;

			e.preventDefault();
			hiddenitems.toggle();
			$(this).children(\'.show_more\').toggleClass(\'active\');
			if(hiddenitems.is(\':visible\')) {
				jQuery(this).children(\'.show_more\').text(\'-\' + cant);
			} else {
				jQuery(this).children(\'.show_more\').text(\'+\' + cant);
			}
		});
		jQuery(\'.pagopar_payments > li\').find(\'input[type=radio]:checked\').parents(\'li\').addClass(\'active\');
		jQuery(".pagopar_payments > li > label").click(function(){
			jQuery(this).parents(\'.pagopar_payments\').children(\'li\').removeClass(\'active\');
			jQuery(this).parent().addClass(\'active\');
		});



});

</script>';

    
 
    
    /* Utilizado para catastro de tarjetas */
    echo '
        
<style>
    .ocultar {display:none !important;}
    .pagopar-no-display {display:none;}
</style>


<!-- Modal HTML embedded directly into document -->
<div id="modalPagoparTarjetas" class="modal">
  <h4>Guardar tarjeta de crédito/débito</h4>
<p>Esto te permitirá poder pagar más rápido la próxima vez, en el catastro te haremos unas preguntas de seguridad, los datos de tarjeta de crédito no son guardados en este sitio web, sino procesados en un ambiente seguro en Bancard.</p>
<div style="height: 100%; width: 100%; margin: auto" id="iframe-container-pagopar-catrastro"/>Cargando.. aguarde unos segundos..</div>


</div>


<style>
#modalPagoparTarjetas h4 {font-size:18px;}                                
#modalPagoparTarjetas p {font-size:14px;}                                
#iframe-container-pagopar-catrastro {margin:10px 10px 20px 10px;font-size:12px; text-align:center;}

#modalPagoparTarjetas {display:none;}


/* FIXES */
#payment div.payment_box {
	padding: .5rem 28px;
	background: #FFF;
}
#payment ul.pagopar_payments > li {
	margin-bottom: 8px;
}
#payment ul.pagopar_payments > li label {
	padding-left: 20px;
}
.pagopar-copy {
	font-size: 13px !important;
}
#payment label .sub {
	padding-left: 35px;
}
#payment ul.payment_methods > li > .methods_group {
	width: 42%;
}
#payment ul.payment_methods > li > .methods_group .method_item {
	margin-left: 2px;
	margin-right: 2px;
}
/*#payment ul.pagopar_payments > li label { width: 52%; font-family: sans-serif; }*/
.pagopar_payments .methods_group { width: 48%; }
@media (max-width: 767px) {
	#payment ul.payment_methods > li > .methods_group {
		width: 100%;
		padding-left: 30px;
		margin-bottom: 5px
	}
	#payment ul.pagopar_payments > li label { width: 100%; }
	.pagopar_payments .methods_group { width: 100%; }
	#payment div.payment_box {
		padding-top: 15px;
		padding-bottom: 10px
	}
	#payment ul.payment_methods li label {
		margin-bottom: 0;
	}
}

    
</style>';
    

    $urlBasePlugin = plugin_dir_url(__FILE__);

    $tema = 'dark';


    $colorFondoDefectoTema = '#333333';
    $colorBordeDefectoTema = '#333333';

    $tema = get_option('pagopar_footer_tema_base');
    $colorFondoDefectoTema = get_option('pagopar_color_fondo');
    $colorBordeDefectoTema = get_option('pagopar_color_borde_superior');
    $pagopar_ocultar_footer = get_option('pagopar_ocultar_footer');
    #$pagopar_formas_pago = get_option('pagopar_formas_pago');


    # Seteamos valores por defecto
    if ($tema == '')
    {
        $tema = 'dark';
    }

    if ($colorFondoDefectoTema == '')
    {
        $colorFondoDefectoTema = '#333333';
    }

    if ($colorBordeDefectoTema == '')
    {
        $colorBordeDefectoTema = '#333333';
    }

    #$cantidadFormasPago = count($pagopar_formas_pago['resultado']);


    if ($pagopar_ocultar_footer == '1')
    {
        return '';
    }

    #if ($pagopar_formas_pago['resultado']['']){
    #}


    echo '



<style>


.footerPagopar {
  position: relative;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: ' . $colorFondoDefectoTema . ';
  text-align: center;
  color: #FFF;border-top: 1px solid ' . $colorBordeDefectoTema . ';
}

.footerPagopar .container {
  padding-top: 20px;
  padding-bottom: 20px;
}

.light .footerPagopar { background-color: ' . $colorFondoDefectoTema . '; border-top: 1px solid ' . $colorBordeDefectoTema . '; }

.pagopar-methods {
  width: 100%;
  text-align: center;
}

.pagopar-methods ul.list {
  display: block;
  list-style: none;
  margin: 0;
  padding: 0;
  vertical-align: top;
}

.pagopar-methods ul.list li {
  display: inline-block;
  list-style: none;
  margin: 0;
  padding: 0;
  vertical-align: top;
  margin-bottom: 5px;
}

.pagopar-methods ul.list li.method {
  -webkit-transition: all 200ms ease;
  -moz-transition: all 200ms ease;
  -o-transition: all 200ms ease;
  -ms-transition: all 200ms ease;
  transition: all 200ms ease;
  max-width: 46px;
  height: 28px;
  line-height: 28px;
  opacity: 1;
}

.pagopar-methods ul.list li.extra-method.hidden {
  display: none;
  opacity: 0;
}

.pagopar-methods ul.list li.more {
  width: 46px;
  height: 28px;
  background-color: #0f68a8;
  -moz-border-radius: 4px;
  -webkit-border-radius: 4px;
  border-radius: 4px;
  line-height: 28px;
  text-align: center;
  font-size: 14px;
  cursor: pointer;
}

.pagopar-methods ul.list li.more::before { content: "+7"; }
.pagopar-methods ul.list li.more.active { background-color: #0a4f81; }
.pagopar-methods ul.list li.more.active::before { content: "-7"; }

.pagopar-methods ul.list li.method img, .pagopar-methods ul.list li.logo-pagopar img {
  display: block;
  max-width: 100%;
  height: auto;
}

.pagopar-methods ul.list li.logo-pagopar {
  margin-left: 10px;
  max-width: 133px;
}d

@media (max-width: 767px) {
  .pagopar-methods ul.list li.logo-pagopar {
    display: block;
    margin-top: 8px;
    text-align: center;
    max-width: 100%;
  }
  .pagopar-methods ul.list li.logo-pagopar img { display: inline-block; width: 115px; }
}

</style>

<div class="' . $tema . '">
<div class="footerPagopar">
		<div class="container">
			<div class="pagopar-methods">
				<ul class="list">
                                        
                                        <li class="method"><img title="Pagá con VISA a través de Pagopar" alt="Cobrar con Tarjeta de Crédito Visa en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/visa.png" ></li>

                                        <li class="method"><img title="Pagá con Mastercard a través de Pagopar" alt="Cobrar con Tarjeta de Crédito Mastercard en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/mastercard.png" ></li>

                                        <li class="method"><img title="Pagá con American Express a través de Pagopar" alt="Cobrar con Tarjeta de Crédito American Express en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/aex.png" ></li>

                                        <li class="method extra-method hidden"><img title="Pagá con Diners a través de Pagopar" alt="Cobrar con Tarjeta de Crédito Diners en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/diners.png" ></li>

                                        <li class="method extra-method hidden"><img title="Pagá con Credifielco a través de Pagopar" alt="Cobrar con Tarjeta de Crédito Credifielco en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/credifielco.png" ></li>

                                        <li class="method extra-method hidden"><img title="Pagá con Única a través de Pagopar" alt="Cobrar con Tarjeta de Crédito Única en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/unica.png" ></li>

                                        <li class="method extra-method hidden"><img title="Pagá con Credicard a través de Pagopar" alt="Cobrar con Tarjeta de Crédito Credicard en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/credicard.png" ></li>

                                        <li class="method extra-method hidden"><img title="Pagá con Cabal a través de Pagopar" alt="Cobrar con Tarjeta de Crédito Cabal en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/cabal.png" ></li>

                                        <li class="method extra-method hidden"><img title="Pagá con Panal a través de Pagopar" alt="Cobrar con Tarjeta de Crédito Panal en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/panal.png" ></li>

                                        <li class="method extra-method hidden"><img title="Pagá con Pagopar Card a través de Pagopar" alt="Cobrar con Tarjeta de Crédito Pagopar Card en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/pagopar.png" ></li>

                                        <li class="method"><img title="Pagá con Wepa a través de Pagopar" alt="Cobrar con Wepa en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/wepa.png" ></li>
                                        
                                        <li class="method"><img title="Pagá con Pagoexpress a través de Pagopar" alt="Cobrar con Pagoepxress en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/pagoexpress.png" ></li>

                                        <li class="method"><img title="Pagá con Aquí Pago a través de Pagopar" alt="Cobrar con Aqui Pago en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/aquipago.png" ></li>

                                        <li class="method"><img title="Pagá con Practipago a través de Pagopar" alt="Cobrar con Practipago en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/practipago.png" ></li>

                                        <li class="method"><img title="Pagá con Tigo Money a través de Pagopar" alt="Cobrar con Tigo Money en Paraguay - Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/tigo-money.png" ></li>
					
                                        <li class="method"><img title="Pagá con Billetera Personal a través de Pagopar" alt="Cobrar con Billetera Personal en Paraguay - Pagopar"  src="' . $urlBasePlugin . 'images/footer/' . $tema . '/billetera-personal.png" ></li>					

                                        <li class="method"><img title="Pagá con Giros Claro a través de Pagopar" alt="Cobrar con Giros Claro en Paraguay - Pagopar"  src="' . $urlBasePlugin . 'images/footer/' . $tema . '/giros-claro.png" ></li>					

					                    <li class="method"><img title="Pagá con Wally a través de Pagopar" alt="Cobrar con Billetera Personal en Paraguay - Pagopar"  src="' . $urlBasePlugin . 'images/footer/' . $tema . '/wally.png" ></li>					
					
					
                                        <li class="method more"></li>
                                        
					<li class="logo-pagopar">
                                        <a target="_blank" href="https://www.pagopar.com/">
                                            <img title="Vender y cobrar online fácil con Pagopar"  "Vender y cobrar online fácil con Pagopar" src="' . $urlBasePlugin . 'images/footer/' . $tema . '/procesado-por-pagopar.png" alt="Procesado por Pagopar">
                                        </a>    
                                        </li>
				</ul>
				<script type="text/javascript">
                                var $ = jQuery;
					$(".pagopar-methods .list .more").click(function(){
						$(".pagopar-methods .list .extra-method").toggleClass(\'hidden\');
						$(".pagopar-methods .list .more").toggleClass(\'active\');
					});
				</script>
			</div>
		</div>
	</div>    
    </div>    
    
    
    
    
    
    ';

    return '';

}

function make_fields_non_required($fields)
{

    $payments = WC()
        ->payment_gateways
        ->payment_gateways();


    $mostrar_ciudad = show_flete_in_checkout();
    //$mostrarEnvioForzadamente = $payments['pagopar']->settings['mostrar_envio_forzadamente'];
    //$pagoparAexCategory = $payments['pagopar']->settings['configuracion_avanzada_id_categoria_defecto'];
    // if ((!$mostrar_ciudad) and ($mostrarEnvioForzadamente !== 'yes') and ($pagoparAexCategory === '909'))
    // {
    //     unset($fields['billing']['billing_address_1']);
    // }
    // if ($mostrarEnvioForzadamente !== 'yes')
    // {
    //     unset($fields['billing']['billing_company']);
    //     unset($fields['billing']['billing_address_2']);
    //     unset($fields['billing']['billing_city']);
    //     unset($fields['billing']['billing_postcode']);
    //     unset($fields['billing']['billing_country']);
    //     unset($fields['billing']['billing_state']);
    // }
    #$fields['billing']['billing_state']['required'] = 0;
    #unset($fields['billing']['billing_state']['required']);
    $usar_formulario_minimizado = $payments['pagopar']->settings['usar_formulario_minimizado'];
    if ($usar_formulario_minimizado === 'yes')
    {
      unset($fields['billing']['billing_company']);
      unset($fields['billing']['billing_city']);
      unset($fields['billing']['billing_address_2']);
      unset($fields['billing']['billing_postcode']);
      
      if ($payments['pagopar']->settings['eliminar_campo_pais']==='yes'){
          unset($fields['billing']['billing_country']);
          unset($fields['shipping']['shipping_country']);
      }

      unset($fields['shipping']['shipping_company']);
      unset($fields['shipping']['shipping_city']);
      unset($fields['shipping']['shipping_address_2']);
      unset($fields['shipping']['shipping_postcode']);


    }
    return $fields;
}

function unset_wc_pagopar_checkout_fields($fields)
{
    
    /*unset($fields['billing']['billing_first_name']);
    unset($fields['billing']['billing_last_name']);
    unset($fields['billing']['billing_address_1']);
    unset($fields['billing']['billing_email']);
    unset($fields['billing']['billing_phone']);
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_state']);*/

    $payments = WC()
        ->payment_gateways
        ->payment_gateways();
    # Para sobreescribir con datos provenientes de campos alternativos
    $razonSocialAlternativo = $payments['pagopar']->settings['campo_alternativo_razon_social'];
    $rucAlternativo = $payments['pagopar']->settings['campo_alternativo_ruc'];
    $documentoAlternativo = $payments['pagopar']->settings['campo_alternativo_documento'];

    # Se debe eliminar los campos si se usa campos alternativos por como funcionan algunos
    # plugins como Checkout Field Editor for WooCommerce
    # Si se definio un campo alternativo para razon social, eliminamos para evitar duplicacion de campo
    if (($razonSocialAlternativo != '') and ($razonSocialAlternativo != 'billing_razon_social'))
    {
        unset($fields['billing'][$razonSocialAlternativo]);
    }

    # Si se definio un campo alternativo para ruc, eliminamos para evitar duplicacion de campo
    if (($rucAlternativo != '') and ($rucAlternativo != 'billing_ruc'))
    {
        unset($fields['billing'][$rucAlternativo]);
    }

    # Si se definio un campo alternativo para documento, eliminamos para evitar duplicacion de campo
    if (($documentoAlternativo != '') and ($documentoAlternativo != 'billing_documento'))
    {
        unset($fields['billing'][$documentoAlternativo]);
        unset($fields[$documentoAlternativo]);
    }

    return $fields;
}


function pluginInstalado($nombrePlugin) {

    $all_plugins = get_plugins();
    $plugin_instalado = false;
    foreach($all_plugins as $pp){
        if($pp['TextDomain']==$nombrePlugin){
            $plugin_instalado = true;
        }
    }
    return $plugin_instalado;
}



function pagopar_add_fees() {


    ini_set('display_errors', 'off');
    error_reporting(0);

    $checkoutFeesForWoocommerce = pluginInstalado('checkout-fees-for-woocommerce');
    $payment_method = WC()->session->get('chosen_payment_method');

    $pagopar_gastos_administrativos = get_option('pagopar_gastos_administrativos');
    $pagopar_gastos_administrativos_text = get_option('pagopar_gastos_administrativos_text');
    $monto_foot = WC()->cart->get_subtotal();

    $cart = WC()->cart;
    $cart->calculate_totals();
    /*CAPTURAR EL TEXTO DEL FEE*/
    $fees = $cart->get_fees();
    $fee_text = null;
    foreach ($fees as $fee) {
        $fee_text = $fee->name;
    }
    
    if($checkoutFeesForWoocommerce==1){
        $discount_total = WC()->cart->get_discount_total() + (WC()->cart->fee_total*-1);
    }else{
        $discount_total = WC()->cart->get_discount_total();
    }


    #$monto_foot = WC()->cart->total;
    #$shipping_total = WC()->cart->shipping_total();#

    # para obtener el total de envio

    # Obtenemos el id del envio seleccionado
    $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' )[0];

    // Loop through shipping packages from WC_Session (They can be multiple in some cases)
    foreach ( WC()->cart->get_shipping_packages() as $package_id => $package ) {
        // Check if a shipping for the current package exist
        if ( WC()->session->__isset( 'shipping_for_package_'.$package_id ) ) {
            // Loop through shipping rates for the current package
            foreach ( WC()->session->get( 'shipping_for_package_'.$package_id )['rates'] as $shipping_rate_id => $shipping_rate ) {
                if ($chosen_shipping_methods == $shipping_rate->get_id()){
                    $shipping_total        = $shipping_rate->get_cost(); // The cost without tax
                    #$tax_cost    = $shipping_rate->get_shipping_tax(); // The tax cost
                    #$taxes       = $shipping_rate->get_taxes(); // The taxes details (array)
                }
            }
        }
    }


    
    if($_POST['payment']=="pagopar"){

        $monto = 0;

        if(is_numeric($pagopar_gastos_administrativos)){
            $monto = (WC()->cart->get_subtotal()*$pagopar_gastos_administrativos)/100;
        }

        $monto_foot = $monto_foot + $monto + $shipping_total;

        if ($monto>0){
            WC()->cart->add_fee($pagopar_gastos_administrativos_text,$monto,true);
        }
    }else{


        $monto_foot = $monto_foot  + $shipping_total;

        #die('a');
        $fees = WC()->cart->get_fees();
        foreach ($fees as $key => $fee) {
            if($fees[$key]->name === $pagopar_gastos_administrativos_text) {
                unset($fees[$key]);
            }
        }
    }


    $cartfee = WC()->cart->get_fees();
    $chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

    $totalTaxes = 0;
    $cartTaxes = WC()->cart->get_tax_totals();
    $labelTaxesTotal = '';
    foreach ($cartTaxes as $key => $value) {
        $totalTaxes = $totalTaxes + $value->amount;
        $labelTaxesTotal = ' (incluye '.$value->formatted_amount.' '.$value->label.') ';
    }
    $monto_foot = $monto_foot + $totalTaxes;



    # actualizar hold stock con valor seteado en pagopar
    $payments = WC()
        ->payment_gateways
        ->payment_gateways();

    # obtener periodo en dias
    $periodo_dias = $payments['pagopar']->settings['periodOfDaysForPayment'];
    $dias_en_horas = 24*60;

    # obtener periodo en horas
    $horas_en_minutos = 0;
    $periodo_horas = $payments['pagopar']->settings['periodOfHoursForPayment'];
    if($periodo_horas>0){
        $horas_en_minutos = $periodo_horas*60;
    }

    if($horas_en_minutos==0 && ($periodo_dias==0 || empty($periodo_dias)) ){
        $periodo_dias=1;
    }
    $dias_en_horas = ($dias_en_horas*$periodo_dias)+$horas_en_minutos;
    update_option( 'woocommerce_hold_stock_minutes', $dias_en_horas );


    ?>

    <?php foreach ($cartfee as $value):?>
        <?php if ($value->amount>0):?>
              <?php $monto_foot = $monto_foot + $value->amount;?>
            <tr class="fee">
                <th><?php echo $value->name;?></th>
                <td data-title="fee"><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">₲</span>
        <?php echo str_replace(",",".",number_format($value->amount));?></span>
                </td>
            </tr>
        <?php endif;?>
    <?php endforeach;?>


    <?php if($checkoutFeesForWoocommerce==1):?>
    
    
    <?php if((WC()->cart->fee_total*-1)>0):?>
       
        <tr class="fee">
                <th><?php echo $fee_text;?></th>
                <td data-title="fee"><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">₲</span>
                <?php echo str_replace(",",".",number_format(WC()->cart->fee_total));?></span>
                </td>
            </tr>
     
    
     <?php endif;?>
    
    
    <?php endif;?>


    <?php if($_POST['porcentaje']>0):?>
        <?php 
            $monto_porcentaje = (($monto_foot*$_POST['porcentaje'])/100);

        ?>
        <tr class="fee">
                <th>Descuento <?php echo $_POST['porcentaje'];?>% </th>
                <td data-title="fee"><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">₲</span>
                <?php echo str_replace(",",".",number_format($monto_porcentaje*-1));?></span>
                </td>
            </tr>
        
      <?php endif;?>       


    <?php

    if($_POST['porcentaje']>0){
        $monto_porcentaje = (($monto_foot*$_POST['porcentaje'])/100);
        $discount_total+=$monto_porcentaje;
     }
    ?>

    <tr class="order-total">
        <th>Total</th>
        <td data-title="Fee"><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">₲</span>
                <strong><?php echo str_replace(",",".",number_format($monto_foot)) . $labelTaxesTotal;?></strong></span>
        </td>
    </tr>


    <?php


    wp_die();
}

function pagopar_shipping_form($fields)
{
   // print_r($fields);
    /*var_dump($fields->WC_Checkout);
    $fields['shipping']['shipping_address_2'] = array(
        'label' => __('Coordenadas', 'pagopar'),
        'value' => '1',
    );
    return $fields;*/
}


function add_wc_pagopar_billing_fields($fields)
{

    $payments = WC()
        ->payment_gateways
        ->payment_gateways();
    $citiesConsultPagopar = new ConsultPagopar($GLOBALS['version']);
    $citiesConsultPagopar->publicKey = $payments['pagopar']->settings['public_key'];
    $citiesConsultPagopar->privateKey = $payments['pagopar']->settings['private_key'];
    $mostrarEnvioForzadamente = $payments['pagopar']->settings['mostrar_envio_forzadamente'];

    
    // $cities = $citiesConsultPagopar->getCities();
    // $cities_wc_format = array();
    // $cities_wc_format['_blank'] = 'Seleccione una ciudad';
    // if ($cities->respuesta)
    // {
    //     foreach ($cities->resultado as $city)
    //     {
    //         $cities_wc_format[$city
    //             ->ciudad] = $city->descripcion;
    //     }
    // }

    // $fields['billing_address_2']['required'] = false;
    // $fields['billing_address_2']['class'] = array(
    //     'form-row-wide'
    // );
    // $fields['billing_city']['required'] = false;
    // $fields['billing_city']['class'] = array(
    //     'form-row-wide'
    // );

    # Para sobreescribir con datos provenientes de campos alternativos
    $razonSocialAlternativo = $payments['pagopar']->settings['campo_alternativo_razon_social'];
    $rucAlternativo = $payments['pagopar']->settings['campo_alternativo_ruc'];
    $documentoAlternativo = $payments['pagopar']->settings['campo_alternativo_documento'];

    /*print_r($fields);die();*/

    #$fields['billing']['billing_state']['required'] = false;
    #$fields['billing_state']['required'] = false;


    $user = wp_get_current_user();
    $documentoDefecto = get_user_meta($user->id, 'pagopar_documento', true);

    # Si se definio un campo alternativo para razon social, usamos ese
    if (($documentoAlternativo == '') or ($documentoAlternativo == 'billing_documento'))
    {
        $fields['billing_documento'] = array(
            'label' => __('Documento (CI)', 'pagopar') ,
            'class' => array(
                'form-row-wide',
                'input-field'
            ) ,
            'placeholder' => _x('Cédula de Identidad', 'placeholder', 'pagopar') ,
            'clear' => false,
            'required' => true,
            'default' => $documentoDefecto
        );

    }

    $mostrar_ciudad = show_flete_in_checkout();
    $pagoparAexCategory = $payments['pagopar']->settings['configuracion_avanzada_id_categoria_defecto'];
    #if (($mostrar_ciudad) or ($mostrarEnvioForzadamente==='yes')) {
    if ($mostrar_ciudad and $pagoparAexCategory == '')
    {
        /*$fields['billing_ciudad'] = array(
            'type' => 'select',
            'class' => array(
                'form-row-first'
            ) ,
            'label' => __('Ciudad') ,
            'options' => $cities_wc_format,
            'required' => $mostrar_ciudad,
        );*/
        /*$fields['billing_calcular'] = array(
            'label' => __('Calcular envío', 'pagopar') ,
            'type' => 'button',
            'class' => array(
                'form-row-second'
            ) ,
            'required' => false,
        );*/
        /*$fields['billing_referencia'] = array(
            'label' => __('Referencia', 'pagopar') ,
            'class' => array(
                'form-row-wide'
            ) ,
            'placeholder' => _x('Referencia de dirección', 'placeholder', 'pagopar') ,
            'required' => false,
            'clear' => false
        );*/
    }

    # Si se definio un campo alternativo para razon social, eliminamos para evitar duplicacion de campo
    if (($razonSocialAlternativo != '') and ($razonSocialAlternativo != 'billing_razon_social'))
    {
        unset($fields[$razonSocialAlternativo]);
    }

    # Si se definio un campo alternativo para ruc, eliminamos para evitar duplicacion de campo
    if (($rucAlternativo != '') and ($rucAlternativo != 'billing_ruc'))
    {
        unset($fields[$rucAlternativo]);
    }

    # Si se definio un campo alternativo para documento, eliminamos para evitar duplicacion de campo
    if (($documentoAlternativo != '') and ($documentoAlternativo != 'billing_documento'))
    {
        unset($fields[$documentoAlternativo]);
    }

    # Si se definio un campo alternativo para razon social, usamos ese
    if (($razonSocialAlternativo == '') or ($razonSocialAlternativo == 'billing_razon_social'))
    {

        $razonSocialDefecto = get_user_meta($user->id, 'pagopar_razon_social', true);

        $fields['billing_razon_social'] = array(
            'label' => __('Razón Social', 'pagopar') ,
            'class' => array(
                'form-row-wide',
                'input-field'
            ) ,
            'placeholder' => _x('Razón Social', 'placeholder', 'pagopar') ,
            'required' => false,
            'clear' => false,
            'default' => $razonSocialDefecto,
        );

    }

    # Si se definio un campo alternativo para ruc, usamos ese
    if (($rucAlternativo == '') or ($rucAlternativo == 'billing_ruc'))
    {
        $rucDefecto = get_user_meta($user->id, 'pagopar_ruc', true);

        $fields['billing_ruc'] = array(
            'label' => __('RUC', 'pagopar') ,
            'class' => array(
                'form-row-wide',
                'input-field'
            ) ,
            'placeholder' => _x('RUC', 'placeholder', 'pagopar') ,
            'required' => false,
            'clear' => false,
            'default' => $rucDefecto,
        );
    }

    /*$fields['billing_metodo_pago'] = array(
        'type' => 'text',
        'label' => __('billing_metodo_pago', 'pagopar') ,
        'class' => array(
            'form-row-wide',
            'pagopar-no-display'
        ) ,
        'required' => false,
        'clear' => false,
        'required' => false
    );*/

    //var_dump($fields);die();

      #obtener metodo seleccionado, si es mobi las coordenadas deben de ser obligatorias
      $mobi_activo = get_option('pagopar_mobi_activo_general');
      if($mobi_activo==1) {
          $fields['billing_coordenadas'] = array(
              'label' => __('Coordenadas', 'pagopar') ,
              'class' => array(
                  'form-row-wide',
                  'input-field'
              ) ,
              'placeholder' => _x('Coordenadas', 'placeholder', 'pagopar') ,
              'required' => true,
              'clear' => false,
              'default' => '',
          );
      }


    return $fields;
}

function show_flete_in_checkout()
{

    foreach (WC()
                 ->cart
                 ->get_cart() as $cart_item)
    {
        $item = $cart_item['data'];
        # Obtenemos el parent ID, esto se hace ya que si el producto es variable el ID del producto varía por cada tipo de variacion
        if ((is_numeric($item->get_parent_id())) and ($item->get_parent_id() > 0))
        {
            $idProductoReal = $item->get_parent_id();
        }
        else
        {
            $idProductoReal = $item->get_id();
        }
        $pp_parent_id = explode(",", get_post_meta($idProductoReal, 'pagopar_cats_id', true));
        $pagopar_parent_id = (int)reset($pp_parent_id);
        if ($pagopar_parent_id == 906)
        { //Si la categoria padre es igual a producto, mostrar el flete.
            return true;
        }
    }
    return false;
}


//Enqueuing javascript scripts
function ajax_pagopar_enqueue_scripts()
{

    if (is_checkout())
    {
        global $post;
        global $origin_pagopar;
        $nonce = wp_create_nonce("nonce_t");
        $deps = array(
            'jquery',
            'jquery-ui-core'
        );
        $version = explode(' ', $GLOBALS['version']);
        $version = $version[1];
        #$version = rand(999999,999999999);
        $in_footer = true;
        wp_enqueue_script('url', plugins_url('js/checkout.js?v1.3', __FILE__) , $deps, $version, $in_footer);
        wp_localize_script('url', 'urlm', array(
            'ajax_url' => admin_url('admin-ajax.php') ,
            'nonce' => $nonce,
            'js_url' => plugins_url('js/pagopar-mapa.js', __FILE__)
        ));
        
        
        wp_enqueue_script('url-modal-js', plugins_url('js/jquery.modal.min.js', __FILE__) , $deps, $version, $in_footer);
        wp_enqueue_style('url-modal-css', plugins_url('css/jquery.modal.min.css', __FILE__));


        wp_enqueue_script( 'bancard-checkout-2.1.0-js', plugins_url( 'js/bancard-checkout-2.1.0.js', __FILE__ ));
        
        
        wp_enqueue_script('leaflet.js', plugins_url('js/leaflet.js', __FILE__));
        wp_enqueue_style('leaflet.css', plugins_url('css/leaflet.css', __FILE__));
        //wp_enqueue_script('pagopar-mapa.js', plugins_url('js/pagopar-mapa.js?v=1.9', __FILE__));

        add_action('wp_ajax_pagopar_agregar_tarjeta', 'pagopar_agregar_tarjeta');
        add_action('wp_ajax_nopriv_pagopar_agregar_tarjeta', 'pagopar_agregar_tarjeta');


    }

    # Fix, soluciona problema que hay con billing_state y select2
    if (class_exists('woocommerce'))
    {
        wp_dequeue_style('selectWoo');
        wp_deregister_style('selectWoo');

        wp_dequeue_script('selectWoo');
        wp_deregister_script('selectWoo');

    }
}

// Adding filter to show pagopar checkout
function pagopar_checkout()
{

    if (!wp_verify_nonce($_POST['nonce'], 'nonce_t')) wp_die();

    add_filter('woocommerce_update_order_review_fragments', 'update_wc_pagopar_billing', 10, 1);
    do_action('woocommerce_update_order_review_fragments');

    # Actualizar  billing_country y shipping_country si no existe por defecto
    $user = wp_get_current_user();
    $billing_country = get_post_meta($user->id, 'billing_country', true);
    $shipping_country = get_post_meta($user->id, 'shipping_country', true);
    if(empty($billing_country) || empty($shipping_country)){
        @update_user_meta($user->id, 'billing_country', 'PY' );
        @update_user_meta($user->id, 'shipping_country', 'PY' );
    }
    
    wp_die();
}

function non_pagopar_checkout()
{
    if (!wp_verify_nonce($_POST['nonce'], 'nonce_t')) wp_die();

    add_filter('woocommerce_update_order_review_fragments', 'update_wc_other_billing', 10, 1);
    do_action('woocommerce_update_order_review_fragments');
   // do_action( 'pagopar_fees_gastos_administrativos', 'no' );
    wp_die();
}

function update_wc_other_billing($array)
{

    # Obtenemos el json de campos con los datos completados para mantener los datos ingresados
    if (isset($_POST['campos']))
    {
        $camposArray = $_POST['campos'];
        foreach ($camposArray as $key => $value)
        {
            $_POST[$key] = $value;
        }
    }

    /* Get normal checkout form*/


    WC()->checkout()
        ->checkout_form_billing();
}


function update_wc_pagopar_billing($array)
{
    #unset_wc_pagopar_checkout_fields();
    /* Add custom fields for pagopar */
    add_filter('woocommerce_billing_fields', 'add_wc_pagopar_billing_fields', 10, 1);

    # Obtenemos el json de campos con los datos completados para mantener los datos ingresados
    if (isset($_POST['campos']))
    {
        $camposArray = $_POST['campos'];
        foreach ($camposArray as $key => $value)
        {
            $_POST[$key] = $value;
        }

    }

    WC()->checkout()
        ->checkout_form_billing($array);



}

function customise_checkout_field_process()
{

    $payments = WC()
        ->payment_gateways
        ->payment_gateways();


    $mostrarEnvioForzadamente = $payments['pagopar']->settings['mostrar_envio_forzadamente'];
    $documentoAlternativo = $payments['pagopar']->settings['campo_alternativo_documento'];
    $pagoparAexCategory = $payments['pagopar']->settings['configuracion_avanzada_id_categoria_defecto'];

    $mostrar_ciudad = show_flete_in_checkout();

    if ((!$mostrar_ciudad) or ($mostrarEnvioForzadamente !== 'yes') and ($pagoparAexCategory == ""))
    {
        // if the field is set, if not then show an error message.
        if (array_key_exists('billing_ciudad', $_POST)) if ($_POST['billing_ciudad'] == '_blank') wc_add_notice(__('Selecciona una ciudad.') , 'error');
    }

    $user = wp_get_current_user();

	
    // if the field is set, if not then show an error message.
    if (array_key_exists('billing_documento', $_POST))
    {
        if ($_POST['billing_documento'] == '')
        {
            wc_add_notice(__('Número de documento (CI) es un campo requerido.') , 'error');
        }
        else
        {
            # Actualizamos documento ci
            # Si se definio un campo alternativo para documento, eliminamos para evitar duplicacion de campo
            if (($documentoAlternativo != '') and ($documentoAlternativo != 'billing_documento'))
            {
                @update_user_meta($user->id, 'pagopar_documento', $documentoAlternativo);
            }
            else
            {
                @update_user_meta($user->id, 'pagopar_documento', $_POST['billing_documento']);
            }

        }
    }

    # Actualizamos razon social y ruc por defecto
    @update_user_meta($user->id, 'pagopar_razon_social', $_POST['billing_razon_social']);
    @update_user_meta($user->id, 'pagopar_ruc', $_POST['billing_ruc']);


}

function pagopar_checkout_change_price()
{
    if (!wp_verify_nonce($_POST['nonce'], 'nonce_t')) wp_die();
    ?>
    <?php
    global $woocommerce;
    global $order_id;

    $user = wp_get_current_user();
    $last_order = wc_get_customer_last_order($user->id);

    $order = wc_get_order($last_order->id);

    /* Agregar costo de envio */

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
    $new_ship_price = intval($_POST['envio']);

    // Get a new instance of the WC_Order_Item_Shipping Object
    $item = new WC_Order_Item_Shipping();

    $item->set_method_title("Envío - Tarifa por Zonas");
    $item->set_method_id("flat_rate:14"); // set an existing Shipping method rate ID
    $item->set_total($new_ship_price); // (optional)
    $item->calculate_taxes($calculate_tax_for);

    $order->add_item($item);

    $order->calculate_totals();

    ?>
    <div id='total'><?php echo wc_price($_POST['total']); ?></div><div id='envio'><?php echo wc_price($_POST['envio']); ?></div>
    <?php
    wp_die();
}

/* CHANGE ORDER REVIEW */

function change_order_review()
{
    if (!wp_verify_nonce($_POST['nonce'], 'nonce_t')) wp_die();

    add_filter('woocommerce_review_order_before_cart_contents', 'update_wc_pagopar_order_review', 10, 1);
    add_action('woocommerce_order_status_pending', 'get_order_json', 10, 1);

    wp_die();
}



function curlNotificarInstalacionComercio($instalacion){

    if(empty($instalacion)){
        $instalacion = "Instalación";
    }
    $root_path = plugin_dir_path( __DIR__ );
    $array = array();
    $array['plugin'] = 'Woocommerce';
    $array['version'] = "2.6.4";
    $array['path_instalacion'] = $root_path;
    $array['php_server_name'] = $_SERVER['SERVER_NAME'];
    $array['tipo_accion_plugin'] = $instalacion;

    $datosJson = json_encode($array);

    //var_dump($datosJson);die();

    $url = "https://api-plugins.pagopar.com/api/instalacion-plugin/1.1/notificar";
    $ch = curl_init();
    $headers = array('Accept: application/json', 'Content-Type: application/json', 'X-Origin: Woocommerce');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datosJson);

    $response = curl_exec($ch);
    curl_close($ch);
    return;
}

function calculate_flete($ciudad_id, $rates) {
    
    

    WC()->session->set('pagopar_order_flete', null);
    WC()->session->set('metodos_envios_flete', null);
    WC()->session->set('metodos_envios_retiro_local', null);

    
    $payments = WC()->payment_gateways->payment_gateways();
    
  

    global $woocommerce;
    $orderPagopar = array();
    $order = WC()->cart->get_cart();

    
    $orderPagopar['tipo_pedido'] = "VENTA-COMERCIO";
    $orderPagopar['fecha_maxima_pago'] = "2020-05-08 14:01:00";
    $orderPagopar['public_key'] = $payments['pagopar']->settings['public_key'];
    $orderPagopar['id_pedido_comercio'] = 1;

    $orderPagopar['monto_total'] = round(WC()->cart->get_cart_contents_total(), 0);
    $orderPagopar['token'] = sha1($payments['pagopar']->settings['private_key'] . "1" . $order->total);
    $orderPagopar['descripcion_resumen'] = "";

    $obtenerMetodoEnvioSeleccionadoPagopar = obtenerMetodoEnvioSeleccionadoPagopar();


    # parche fix, esto hacemos ya que puede que este habilitado aex pero no activo, y debe hacer igual el endpoint calcular flete
    /* if (!is_numeric($ciudad_id)){
      $city_id = 1;
      } */

    if(empty($ciudad_id)){
        $ciudad_id = 1;
    }  

    if($ciudad_id=="-ASU"){
        $ciudad_id = 1;
    }

    $orderPagopar['comprador'] = array(
        "nombre" => "Rudolph Goetz",
        "ciudad" => $ciudad_id,
        "email" => "fernandogoetz@gmail.com",
        "telefono" => "0972200046",
        "tipo_documento" => "CI",
        "documento" => "4247903",
        "direccion" => "Direccion por defecto del comprador para calcular envio",
        "direccion_referencia" => "",
        "coordenadas" => "-25.26080770331157, -57.51165674656511",
        "ruc" => null,
        "razon_social" => null
    );
    $items = [];
    $order_items = WC()->cart->get_cart();
    $metodo_propio = [];


    /* $montoDescuentoAplicadoTotal = 0;
      $cuponesAplicados = WC()->cart->get_applied_coupons();
      foreach ($cuponesAplicados as $key => $value) {
      $coupon = new WC_Coupon($value);
      # Get coupon discount type
      $discount_type = $coupon->get_discount_type();
      # Get coupon amount
      $coupon_amount = $coupon->get_amount();

      $montoDescuentoAplicadoTotal = $montoDescuentoAplicadoTotal + $coupon_amount;
      } */


    # Hay que descontar del monto total sino no va a cuadrar la sumatoria de los items para enviar a Pagopar
    $totalDescuento = intval(WC()->cart->get_discount_total());
    $totalDescuentoRestante = intval($totalDescuento);


    # consultar si existe un cupon de descuento, y si es por porcentaje o por monto
    $applied_coupons = WC()->cart->get_applied_coupons();
    $descuento_monto_cupon=0;
    $tipo_descuento=null;
    foreach ($applied_coupons as $coupon_code) {
        $coupon = new WC_Coupon($coupon_code);
        $tipo_descuento = $coupon->get_discount_type();
        $descuento_monto_cupon = $coupon->get_amount();
        if ($tipo_descuento === 'percent') {
            $tipo_descuento="porcentaje";
        } else {
            $tipo_descuento="monto";
        }
    }
  
    
    //$cantidadProductosCarrito = count($order_items);
    $contadorProductoCarrito = 1;
    $total= 0;

    foreach ($order_items as $item => $values) {
        $_product = wc_get_product($values['data']->get_id());
        $product_id = $values['data']->get_id();
        $price = get_post_meta($product_id, '_price', true);

        $quantity = $values['quantity'];
        $subtotal = $price * $quantity;
        $total = $total + $subtotal;


        if($descuento_monto_cupon>0){
                if($tipo_descuento=="porcentaje"){
                      $precio_con_descuento = ($subtotal*$descuento_monto_cupon)/100;
                      $subtotal = $subtotal - $precio_con_descuento;
                      $total = $total - $subtotal;
                }else{
                    $precio_con_descuento = $subtotal - $descuento_monto_cupon;
                    $subtotal = $subtotal - $precio_con_descuento;
                    $total = $total - $subtotal;
                }
        }else{

            # Parche mientras se habilite descuento en API 2.0 en Pagopar
            $descripcionDescuento = '';
            # SI hay un descuento pendiente por restar para que cuadren los números
            if ($totalDescuentoRestante > 0) {
                $subtotal = $subtotal - $totalDescuentoRestante;
                # Para ver si se necesita volver a descontar en el siguiente item
                $totalDescuentoRestante = $subtotal - $totalDescuentoRestante;
                $descripcionDescuento = ' menos descuento';
            }

         }


        //$link = $product->get_permalink($product);
        // Anything related to $product, check $product tutorial
        //$meta = wc_get_formatted_cart_item_data($product);
        //Verificamos si el producto es virtual o descargable
        $isVirtual = get_post_meta($item->id_producto, '_virtual', true) === "yes";
        $isDownloable = get_post_meta($item->id_producto, '_downloadable', true) === "yes";

        $post_type = get_post_field('post_type', $product_id);
        $cat = get_post_meta($product_id, 'pagopar_final_cat', true);
        #$city_id = get_post_meta($product_id, 'product_seller_ciudad', true);

        $pagopar_direccion_id_woocommerce = get_post_meta($product_id, 'pagopar_direccion_id_woocommerce', true);
        $direccionProducto = traerDirecciones($pagopar_direccion_id_woocommerce);
        $city_id = $direccionProducto[0]->ciudad;



        $idProductoPadre = $product_id;

        $url = urldecode(get_the_post_thumbnail_url($product_id, 'medium'));

        //Validamos que el producto es una variante o un producto padre
        if ($post_type === 'product_variation') {
            $parent_id = get_post_field('post_parent', $product_id);
            $idProductoPadre = $parent_id;

            $urlBk = urldecode(get_the_post_thumbnail_url($parent_id, 'medium'));

            if (trim($urlBk) !== '') {
                $url = $urlBk;
            }


            $pagopar_direccion_id_woocommerce = get_post_meta($parent_id, 'pagopar_direccion_id_woocommerce', true);
            $direccionProducto = traerDirecciones($pagopar_direccion_id_woocommerce);
            $city_id = $direccionProducto[0]->ciudad;
        }


        # Si el producto no tiene direccion asignada, traemos la direccion por defecto
        $direccion_unica_habilitada = get_option('direccion_unica_habilitada');
        //echo $direccion_unica_habilitada;die();

        if (is_numeric($direccion_unica_habilitada)) {
            // traemos la direccion por defecto
            $direccionProductoDefecto = traerDireccionDefecto();
            $city_id = $direccionProductoDefecto->ciudad;
            $direccionProducto = traerDirecciones($direccionProductoDefecto->id);
        }
        
        $cat = get_post_meta($idProductoPadre, 'pagopar_final_cat', true);
        $weight = get_post_meta($idProductoPadre, 'product_weight', true);
        $largo = get_post_meta($idProductoPadre, 'pagopar_largo', true);
        $ancho = get_post_meta($idProductoPadre, 'pagopar_ancho', true);
        $alto = get_post_meta($idProductoPadre, 'pagopar_alto', true);

        # Reemplazamos la coma por punto
        $weight = str_replace(',','.',$weight);
        $largo = str_replace(',','.',$largo);
        $ancho = str_replace(',','.',$ancho);
        $alto = str_replace(',','.',$alto);
        
        
        # Si no se definieron los valores categoria, alto, largo, ancho o peso, se intenta tomar de los valores generales de woocommerce
        if ((!is_numeric($alto)) or ( !is_numeric($largo)) or ( !is_numeric($ancho)) or ( !is_numeric($weight)) or ( !is_numeric($cat))) {
            $weight = get_post_meta($idProductoPadre, '_weight', true);
            $largo = get_post_meta($idProductoPadre, '_length', true);
            $ancho = get_post_meta($idProductoPadre, '_width', true);
            $alto = get_post_meta($idProductoPadre, '_height', true);
            
            
            # Reemplazamos la coma por punto
            $weight = str_replace(',','.',$weight);
            $largo = str_replace(',','.',$largo);
            $ancho = str_replace(',','.',$ancho);
            $alto = str_replace(',','.',$alto);
            if (trim($cat) == '') {
                $cat = 979;
            }
        }




        # aqui hacer que tome valores de peso y demas generico


        $metodo_propio = [];

        $pagopar_envio_aex_pickup_horario_fin = intval(get_post_meta($idProductoPadre, 'pagopar_envio_aex_pickup_horario_fin', true));
        if ($pagopar_envio_aex_pickup_horario_fin === 0) {
            $pagopar_envio_aex_pickup_horario_fin = 48;
        }
        # Armamos envio propio
        foreach ($rates as $rate_key => $rate) {
            # Los flat_rate  de Woocommerce son los que consideramos como envio propio en Pagopar
            if ($rates[$rate_key]->method_id === 'flat_rate') {
                # Hacemos esto ya que si hay mas de un producto, el segundo producto ya no tiene costo en envio propio, en el primer item ya está el precio
                if ($contadorProductoCarrito>1){
                    $costoEnvioPropio = 0;
                }else{
                    $costoEnvioPropio = floatval($rates[$rate_key]->cost);
                }
                $propio = array(
                    "tiempo_entrega" => $pagopar_envio_aex_pickup_horario_fin,
                    "destino" => $ciudad_id,
                    "precio" => $costoEnvioPropio
                );
                array_push($metodo_propio, $propio);
            }else{

                 if (class_exists('WC_Custom_Shipping_Method')) {
                  

                    if ($contadorProductoCarrito>1){
                        $costoEnvioPropio = 0;
                    }else{
                        $costoEnvioPropio = floatval($rates[$rate_key]->cost);
                    }
                    $propio = array(
                        "tiempo_entrega" => $pagopar_envio_aex_pickup_horario_fin,
                        "destino" => $ciudad_id,
                        "precio" => $costoEnvioPropio
                    );
                    array_push($metodo_propio, $propio);


                }else{

                    $propio = array(
                        "tiempo_entrega" => 1,
                        "destino" => 1,
                        "precio" => 15000
                    );
                    array_push($metodo_propio, $propio);

                }
            }
        }

        # Armamos recogida del local
        $retiroSucursal = [];
        foreach ($rates as $rate_key => $rate) {
            # Los local_pickup de Woocommerce son los que consideramos como retiro de sucursal en Pagopar
            if ($rates[$rate_key]->method_id === 'local_pickup') {
                $recogidaLocal = array(
                    "observacion" => $rates[$rate_key]->label
                );
                $retiroSucursal2['observacion'] = $rates[$rate_key]->label;
                array_push($retiroSucursal, $recogidaLocal);
            }
        }


        $product_instance = wc_get_product($product_id);
        $product_full_description = $product_instance->get_description();
        # Reemplazar por los valores de direcciones    
        $mobi['id'] = null;
        $mobi['costo'] = null;
        /******ARMAR ARRAY DE DIAS Y HORAS MOBI******/
        $pagopar_mobi_hora = get_option('pagopar_mobi_hora');
        $pagopar_mobi_hora = json_decode($pagopar_mobi_hora,true);
        if(count((array)$pagopar_mobi_hora)>0){
            for ($var = 0; $var < count((array)$pagopar_mobi_hora); $var++){
                $array_dias = explode(',',($pagopar_mobi_hora[$var]['dias']));
                $array_dias = array_map('trim',$array_dias);
                $mobi['horarios'][$var]['dias']=($array_dias);
                $mobi['horarios'][$var]['pickup_fin'] = $pagopar_mobi_hora[$var]['hora_fin'];
                $mobi['horarios'][$var]['pickup_inicio'] = $pagopar_mobi_hora[$var]['hora_inicio'];
            }
        }

        $mobi_activo = get_option("pagopar_mobi_activo_general");
        //echo get_option("pagopar_mobi_activo_general");
        //die();
        /*$mobi['horarios'][1]['dias'] = array("1", "2", "3", "4", "5");
        $mobi['horarios'][1]['pickup_fin'] = "18:00";
        $mobi['horarios'][1]['pickup_inicio'] = "08:00";*/
        /******ACA TENGO QUE ENVIAS LOS VALORES QUE SE GUARDARON*****/

        #$mobi['opciones'] = array();
        # sino esta habilitado descomentar
        #$mobi = null;

        //"forma_pago"=>"2",

        /*
          para aex en raiz
         * "comentario_pickup":"09:00",
          "disponible_hasta":"09:00:00",
          "disponible_desde":"16:00:00",


          averiguar que son estaos campos que usa hendyla
         * "cantidad_total":1,
          "direccion_principal":null,
          "direcciones":[
          ]


         * 
         *          */

        /* if (!is_numeric($city_id)){
          $city_id = 1;
          } */



        #vendedor_direccion_coordenadas ni vendedor_direccion, ni comprador.coordenadas, vendedor_direccion_referencia no puede ser vacio para mobi
        #$cat = '4988';#temp, enviando 979 no está funcionando, tampoco con 1229
        //$direccionProducto[0]->id

        # Si es flotante, usamos así para que ya que 1.00 es considerado float
        $adicionalNombreCantidad = '';
        if ((fmod($quantity, 1) !== 0.00)===true){
                $quantity = 1;
                $adicionalNombreCantidad = ' - Cantidad: '.$quantity;
        }

         if(empty($quantity)){
            $quantity = 1;
         }
    
         if($quantity=="-ASU"){
            $quantity = 1;
         }

        $itemPagopar = array(
            "nombre" => $values['data']->get_title() . $adicionalNombreCantidad,
            "cantidad" => $quantity,
            "precio_total" => $subtotal,
            "ciudad" => $city_id,
            "descripcion" => $values['data']->get_title() . $descripcionDescuento,
            "url_imagen" => $url,
            "peso" => $weight,
            "vendedor_telefono" => $direccionProducto[0]->telefono,
            "vendedor_direccion" => $direccionProducto[0]->direccion,
            "vendedor_direccion_referencia" => $direccionProducto[0]->direccion_referencia,
            "vendedor_direccion_coordenadas" => $direccionProducto[0]->direccion_coordenadas,
            "public_key" => $payments['pagopar']->settings['public_key'],
            "categoria" => ($isVirtual || $isDownloable) ? 909 : $cat,
            "id_producto" => $product_id,
            "largo" => $largo,
            "ancho" => $ancho,
            "alto" => $alto,
            "opciones_envio" => array(
                "metodo_retiro" =>
                $retiroSucursal2
                ,
                "metodo_propio" => array(
                    "listado" => $metodo_propio
                ),
                "metodo_mobi" => $mobi
            )
        );


        array_push($items, $itemPagopar);
        $contadorProductoCarrito = $contadorProductoCarrito + 1;
    }
    $orderPagopar['compras_items'] = $items;

    $jsonObject = array();
    $jsonObject = $orderPagopar; // para la version 2.0 de calcular flete
    $jsonObject['token'] = sha1($payments['pagopar']->settings['private_key'] . "CALCULAR-FLETE");
    $jsonObject['token_publico'] = $payments['pagopar']->settings['public_key'];



    $args = json_encode($jsonObject);


    $resultado = curlRun($args, 'https://api-plugins.pagopar.com/api/calcular-flete/2.0/traer');
    $response = json_decode($resultado);

    # VOlvemos a calcular flete y seleccionamos ya el envio
    $jsonFlete = $response;
    $itemSeleccionarEnvio = $jsonFlete->compras_items;

    ini_set('display_errors', 'off');
    error_reporting(0);
    foreach ($itemSeleccionarEnvio as $key => $value) {
        //$itemSeleccionarEnvio[$key]['envio_seleccionado'] = ($isVirtual || $isDownloable) ? false : $obtenerMetodoEnvioSeleccionadoPagopar['metodo_seleccionado'];
        $itemSeleccionarEnvio[$key]->envio_seleccionado = $obtenerMetodoEnvioSeleccionadoPagopar['metodo_seleccionado'];

        if ($obtenerMetodoEnvioSeleccionadoPagopar['metodo_seleccionado'] === 'aex') {
            $itemSeleccionarEnvio[$key]->opciones_envio->metodo_aex->id = $obtenerMetodoEnvioSeleccionadoPagopar['metodo_seleccionado_opcion'];
        } elseif ($obtenerMetodoEnvioSeleccionadoPagopar['metodo_seleccionado'] === 'mobi') {
            $itemSeleccionarEnvio[$key]->opciones_envio->metodo_mobi->id = $obtenerMetodoEnvioSeleccionadoPagopar['metodo_seleccionado_opcion'];
            #$itemPagopar['opciones_envio']->metodo_mobi->id = $obtenerMetodoEnvioSeleccionadoPagopar['metodo_seleccionado_opcion'];
            #$itemPagopar['opciones_envio']->metodo_mobi->costo = 100;
        }
    }

    $jsonFleteSeleccionarEnvio = $jsonFlete;
    $jsonFleteSeleccionarEnvio->compras_items = $itemSeleccionarEnvio;

    $resultado = curlRun(json_encode($jsonFleteSeleccionarEnvio), 'https://api-plugins.pagopar.com/api/calcular-flete/2.0/traer');
    $response = json_decode($resultado);
    WC()->session->set('pagopar_order_flete', $resultado);
    WC()->session->set('metodos_envios_flete', json_encode($metodo_propio));
    WC()->session->set('metodos_envios_retiro_local', json_encode($retiroSucursal2));

    
  
    return $response;
}

function curlRun($json, $url){
    
    
    $ch = curl_init();
    $headers = array('Accept: application/json', 'Content-Type: application/json', 'X-Origin: Woocommerce');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, "https://api-plugins.pagopar.com/api/calcular-flete/2.0/traer");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
    
}


function set_flete()
{
    if (!wp_verify_nonce($_POST['nonce'], 'nonce_t')) wp_die();

    $ok_json = stripslashes($_POST['json']);

    if ($ok_json)
    {

        $envios = json_decode($ok_json, true);
        $symbol = get_woocommerce_currency_symbol();
        ?>
        <thead>
        <tr>
            <th class="product-name">Producto</th>
            <th class="product-total">Total</th>
            <th class="product-name">Envío</th>
        </tr>
        </thead>
        <tbody>
        <?php
        global $woocommerce;
        $i = 0;
        foreach (WC()
                     ->cart
                     ->get_cart() as $index => $cart_item)
        {
            $item = $cart_item['data'];

            // Hidden field
            woocommerce_wp_hidden_input(array(
                'id' => 'envio_seleccionado_' . $item->get_id() . '_hidden',
                'value' => '',
            ));
            if (!empty($item))
            {

                # Obtenemos el parent ID, esto se hace ya que si el producto es variable el ID del producto varía por cada tipo de variacion
                /*if ((is_numeric($item->get_parent_id())) and ( $item->get_parent_id() > 0)) {
                      $idProductoReal = $item->get_parent_id();
                      } else {
                      $idProductoReal = $item->get_id();
                      } */

                # Obtenemos datos del producto, segun sea producto variable o producto normal
                if ((is_numeric($item->get_parent_id())) and ($item->get_parent_id() > 0))
                {
                    $product = new WC_Product_Variation($item->get_id());
                    $idParentItemRetiro = $item->get_parent_id(); // Para obtener si está habilitado retiro de sucursal, se utiliza el id del padre o el id del producto si no es variable

                }
                else
                {
                    $product = new WC_Product($item->get_id());
                    $idParentItemRetiro = $item->get_id();
                }

                $qty = $cart_item['quantity'];
                ?>
                <tr class="cart_item">
                    <td class="product-name">
                        <a href="<?php echo site_url() . $product->get_name(); ?>"><?php echo $product->get_name(); ?></a> &nbsp;
                        <strong class="product-quantity">× <?php echo $qty; ?></strong>
                    </td>
                    <td class="product-total">
                        <?php
                        $display_price = '0';
                        $value_price = 0;
                        $sale_price = $product->get_sale_price();
                        $regular_price = $product->get_regular_price();

                        if ($sale_price)
                        {
                            $display_price = wc_price($sale_price * $qty);
                            $value_price = $sale_price * $qty;
                        }
                        else
                        {
                            $display_price = wc_price($regular_price * $qty);
                            $value_price = $regular_price * $qty;
                        }
                        ?>
                        <span class="woocommerce-Price-amount amount" price="<?php echo $value_price; ?>">
                                <?php echo $display_price; ?>
                            </span>
                    </td>
                    <td class="product-total columnaEnvio">
                        <?php

                        $retiroOn = get_post_meta($idParentItemRetiro, 'product_enabled_retiro', true);
                        if (array_key_exists($item->get_id() , $envios))
                        {
                            foreach ($envios[$item->get_id() ] as $method => $value)
                            {
                                if ($value)
                                {
                                    if ((isset($value['costo']) && isset($value['tiempo_entrega'])) //AEX o PROPIO
                                        or (isset($value['observacion']) and $retiroOn == "yes") //RETIRO
                                    )
                                    {
                                        $costo = null;
                                        $obs = null;
                                        if ($method === "aex" or $method === "propio")
                                        {
                                            $costo = $value['costo'];
                                            $obs = "Entrega en " . $value['tiempo_entrega'] . ' hs.';
                                        }
                                        elseif ($method === "retiro")
                                        {
                                            $costo = 0;
                                            $obs = $value['observacion'];
                                        }
                                        ?>
                                        <div class="radio">
                                            <div id="metodo_<?php echo $method . '_' . $item->get_id(); ?>">
                                                <label style="font-size: 11px;">
                                                    <input <?php echo (($costo === 0) and ($method === 'aex')) ? ' onclick="alert(\'Este precio es solo si selecciona AEX en el primer item\');return false;" ' : ''; ?> type="radio" name="envio_seleccionado_<?php echo $item->get_id(); ?>" id="envio_seleccionado_<?php echo $item->get_id(); ?>"
                                                                                                                                                                                                                         identificador="<?php echo $method; ?>" class="envio_seleccionado opcion_<?php echo $method; ?>"
                                                                                                                                                                                                                         price="<?php echo $costo; ?>" value="<?php echo $method; ?>" <?php echo (($costo === 0) and ($method !== 'aex')) ? "checked" : ''; ?> >
                                                    <span style="text-transform:uppercase;"><?php echo $method; ?></span>
                                                    <strong class="precio_entrega_<?php echo $method; ?>"> - <?php echo $costo; ?> Gs.</strong><br>
                                                    <em class="entrega_<?php echo $method; ?>"><?php echo $obs; ?></em>
                                                </label>
                                            </div>
                                        </div>

                                        <?php
                                    }
                                }
                            }
                        }
                        else
                        {
                            ?>
                            <p>Sin costo de envío</p>
                            <?php
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
            $i++;
        }
        ?>
        <tfoot>

        <?php
        # Obtenemos los impuestos
        $taxes = WC()
            ->cart
            ->get_tax_totals();
        foreach ($taxes as $key => $value):
            echo $value->amount;
            echo $value->label;
            echo $value->formatted_amount;
            $claseTax = 'tax-rate tax-rate-' . str_replace(' ', '-', strtolower($key));
            ?>
            <tr class="tax-rate <?php echo $claseTax; ?>">
                <th>
                    <?php
                    # Array Impuestos
                    $taxes = WC()
                        ->cart
                        ->get_tax_totals();
                    # Total impuestos
                    $totalTaxes = WC()
                        ->cart->tax_total;
                    foreach ($taxes as $key => $value)
                    {
                        $claseTax = 'tax-rate tax-rate-' . str_replace(' ', '-', strtolower($key));
                        echo $value->label;
                        #echo $value->amount;

                    }
                    #var_dump($taxes);

                    ?>

                </th>
                <td><?php echo $value->formatted_amount; ?></td>
            </tr>
        <?php
        endforeach;
        //var_dump($taxes);

        ?>





        <tr class="cart-subtotal" value="0,00">
            <th>Envío</th>
            <td>
                    <span class="woocommerce-Price-amount amount">
                        <span class="woocommerce-Price-currencySymbol"><?php echo $symbol; ?></span>0,00
                    </span>
            </td>
        </tr>
        <tr class="order-total" value="<?php echo (WC()
                ->cart->cart_contents_total + $totalTaxes); ?>">
            <th>Total</th>
            <td>
                <strong>
                        <span class="woocommerce-Price-amount amount">
                            <?php echo $woocommerce
                                ->cart
                                ->get_total(); ?></span>
                </strong>
            </td>
        </tr>
        </tfoot>
        <?php
    }
}

# Acciones de sincronización
add_action( 'save_post', 'exportarProductoInicial', 99, 3);
add_action( 'woocommerce_product_import_inserted_product_object', 'exportarProductoInicialImportacionAsincrona', 99, 2);



add_action( 'woocommerce_reduce_order_stock', 'avisarInventarioCambiadoCompra' );
add_filter( 'the_content', 'woocommerceDescripcionModificada' );

require_once 'pagopar-sincronizacion.php';
require_once 'pagopar-direcciones.php';
require_once 'pagopar-suscripciones.php';

/**
 * Control para que solo se pueda confirmar pedidos con AEX/Mobi si selecciona Pagopar
 * @param type $null
 * @param type $order
 */
function control_courier_crear_pedido($null,$order) {


    # Tratar documento si esta vacio solo si es pagopar
    if ((substr($order['payment_method'], 0, 7)=='pagopar') and ($order['payment_method']!='pagopar_pix')) {

        $payments = WC()
            ->payment_gateways
            ->payment_gateways();
        $documentoAlternativo = $payments['pagopar']->settings['campo_alternativo_documento'];


        if (($documentoAlternativo != '') and ($documentoAlternativo != 'billing_documento'))
        {
            $documento_a_tratar = $_POST[$documentoAlternativo];
        }
        else
        {
            $documento_a_tratar = $_POST['billing_documento'];
        }

        #obtener metodo seleccionado, si es mobi las coordenadas deben de ser obligatorias
        $obtenerMetodoEnvioSeleccionadoPagopar = obtenerMetodoEnvioSeleccionadoPagopar();
        if ($obtenerMetodoEnvioSeleccionadoPagopar['metodo_seleccionado'] === "mobi"){
            if(empty($_POST['billing_coordenadas'])){
                $array = array(
                    'result' => 'failure',
                    'messages' => 'Debes seleccionar las coordenadas en el mapa.'
                );
                wp_die(json_encode($array));
            }
        }


        $documento_a_tratar = str_replace(".","",$documento_a_tratar);

        if (empty($documento_a_tratar)) {
            $array = array(
                'result' => 'failure',
                'messages' => 'El documento no puede estar vacio.'
            );
            wp_die(json_encode($array));
        }

        if(is_numeric($documento_a_tratar)){

            # Tratar documento si no cumple el rango
            if ((strlen($documento_a_tratar) < 5) || (strlen($documento_a_tratar) > 24)) {
                $array = array(
                    'result' => 'failure',
                    'messages' => 'El Documento (CI) debe ser de un rango entre 5 y 24.'
                );
                wp_die(json_encode($array));

            }

        }else{

            $array = array(
                'result' => 'failure',
                'messages' => 'El Documento (CI) no puede ser alfanumerico.'
            );
            wp_die(json_encode($array));
        }

    }

    $array = array(
        'result'   => 'failure',
        'messages' => 'El medio de envio AEX solo está disponible si selecciona cualquiera de los medios de pagos ofrecidos por Pagopar'
    );

    # Si selecciono aex y no Pagopar, no se puede finalizar el pedido, 
    if (strpos($order['shipping_method'][0], "flat_rate_aex") !== false) {
        if (substr($order['payment_method'], 0, 7)!=='pagopar'){
            #wc_add_notice('El medio de envio AEX solo está disponible si selecciona uno de los medios de pagos ofrecidos por Pagopar.');
            wp_die(json_encode($array));
        }
    }


    $array = array(
        'result'   => 'failure',
        'messages' => 'El medio de envio MOBI solo está disponible si selecciona cualquiera de los medios de pagos ofrecidos por Pagopar'
    );

    # Si selecciono mobi y no Pagopar, no se puede finalizar el pedido,
    if (strpos($order['shipping_method'][0], "flat_rate_mobi") !== false) {
        if (substr($order['payment_method'], 0, 7)!=='pagopar'){
            #wc_add_notice('El medio de envio MOBI solo está disponible si selecciona uno de los medios de pagos ofrecidos por Pagopar.');
            wp_die(json_encode($array));
        }
    }
    
    
     if ($order['payment_method']=='pagopar_pix') {
         
         $payments = WC()
            ->payment_gateways
            ->payment_gateways();
        $documentoAlternativo = $payments['pagopar']->settings['campo_alternativo_documento'];


        if (($documentoAlternativo != '') and ($documentoAlternativo != 'billing_documento'))
        {
            $documento_a_tratar = $_POST[$documentoAlternativo];
        }
        else
        {
            $documento_a_tratar = $_POST['billing_documento'];
        }
        
        
   
       if (validaCPForCNPJ($documento_a_tratar)===false){
           
           
             $array = array(
                'result'   => 'failure',
                'messages' => 'No corresponde documento CPF o CPNJ. Ingrese un CPF o CPNJ válido en el campo documento.'
            );
            wp_die(json_encode($array));
            
           
       }
         
         
     }
    
    

}
add_filter( 'woocommerce_checkout_create_order', 'control_courier_crear_pedido', 1, 2);






function validaCPForCNPJ($numero) {
    // Elimina cualquier cosa que no sea un número
    $numero = preg_replace('/[^0-9]/', '', $numero);

    // Determina si es CPF o CNPJ basado en la longitud
    if (strlen($numero) === 11) {
        return validaCPF($numero);
    } elseif (strlen($numero) === 14) {
        return validaCNPJ($numero);
    }

    // No es ni CPF ni CNPJ
    return false;
}

function validaCPF($cpf) {
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }

    return true;
}

function validaCNPJ($cnpj) {
    if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }

    $soma = 0;
    $soma = calcularSomaCNPJ(substr($cnpj, 0, 12), 5) + calcularSomaCNPJ(substr($cnpj, 0, 12), 6);
    $resto = $soma % 11;
    $digito1 = $resto < 2 ? 0 : 11 - $resto;
    if ($cnpj[12] != $digito1) {
        return false;
    }

    $soma = calcularSomaCNPJ(substr($cnpj, 0, 13), 6) + $digito1 * 2;
    $resto = $soma % 11;
    $digito2 = $resto < 2 ? 0 : 11 - $resto;
    if ($cnpj[13] != $digito2) {
        return false;
    }

    return true;
}

function calcularSomaCNPJ($numeros, $posicaoInicial) {
    $soma = 0;
    for ($i = 0; $i < strlen($numeros); $i++) {
        $soma += $numeros[$i] * $posicaoInicial;
        $posicaoInicial = $posicaoInicial - 1 === 1 ? 9 : $posicaoInicial - 1;
    }
    return $soma;
}








function filter_woocommerce_shipping_calculator_enable_city( $false ) { 
    $payments = WC()->payment_gateways->payment_gateways();

    $usar_formulario_minimizado = $payments['pagopar']->settings['usar_formulario_minimizado'];
    if ($usar_formulario_minimizado === 'yes'){
        return false; 
    }else{
        return true;        
    }
}; 
add_filter( 'woocommerce_shipping_calculator_enable_city', 'filter_woocommerce_shipping_calculator_enable_city', 10, 1 ); 


function filter_woocommerce_shipping_calculator_enable_postcode( $false ) { 
    $payments = WC()->payment_gateways->payment_gateways();

    $usar_formulario_minimizado = $payments['pagopar']->settings['usar_formulario_minimizado'];
    if ($usar_formulario_minimizado === 'yes'){
        return false; 
    }else{
        return true;        
    }
}; 
add_filter( 'woocommerce_shipping_calculator_enable_postcode', 'filter_woocommerce_shipping_calculator_enable_postcode', 10, 1 ); 

function pagopar_cron_schedules($schedules){
    if(!isset($schedules["24hrs"])){
        $schedules["24hrs"] = array(
            'interval' => 86400,
            'display' => __('Cada 24 horas'));
    }
    return $schedules;
}
add_filter('cron_schedules','pagopar_cron_schedules');

if (!wp_next_scheduled('pagopar_task_hook')) {
  wp_schedule_event(time(), '24hrs', 'pagopar_task_hook');
}
add_action ( 'pagopar_task_hook', 'pagopar_task_function' );

function expiroCacheJSON($fecha, $minutosComparacion = 60) {
    $fechaActual = time(); // Obtiene la fecha actual en formato UNIX timestamp
    $minutosPasados = ($fechaActual - strtotime($fecha)) / 60; // Calcula los minutos pasados entre la fecha recibida y la fecha actual
    return $minutosPasados >= $minutosComparacion; // Retorna true si han pasado 1440 minutos o más, false en caso contrario
}


function pagoparCacheCurl($valorJSON, $pagoparCiudadesFecha){

    $ciudadesDB = get_option($valorJSON);
    $ciudadesDBFecha = get_option($pagoparCiudadesFecha);

    $consultarPagopar = false;
    if ($ciudadesDBFecha===false){
        $consultarPagopar = true;
    }else{
        $expiroCacheJSON = expiroCacheJSON($ciudadesDBFecha, 1440);
        if ($expiroCacheJSON===true){
            $consultarPagopar = true;
        }
    }

    return $consultarPagopar;

}

require_once 'pagopar-multimedios-pago.php';
