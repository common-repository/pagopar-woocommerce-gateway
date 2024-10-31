<?php

/* Template Name: ConfirmarPagoparUrl */
?>
<?php

# No mostramos errores
ini_set('display_errors', 'off');
error_reporting(0);

$rawInput = file_get_contents('php://input');


$json_pagopar = json_decode($rawInput, true);

global $wpdb;
$order_db = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM wp_transactions_pagopar WHERE hash = %s ORDER BY id DESC LIMIT 1", $json_pagopar['resultado'][0]['hash_pedido'])
);

#Obtenemos key privado
$db = new DBPagopar(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, "wp_transactions_pagopar");
$pedidoPagopar = new Pagopar(null, $db, $origin_pagopar);
$payments = WC()->payment_gateways->payment_gateways();


if (isset($_GET['sincronizacion-reseteo'])) {

    include_once 'pagopar-sincronizacion.php';

    if (sha1($payments['pagopar']->settings['private_key'] . '') === $json_pagopar['token']) {
        try {

            set_time_limit(0);
            #eliminar todos los datos
            $wpdb->delete( $wpdb->prefix . 'postmeta', array( 'meta_key' => 'pagopar_volver_enviar' ) );
            #recorrer y volver a insertar
            $sql_insert = "INSERT INTO " . $wpdb->prefix . "postmeta ( post_id, meta_key, meta_value ) SELECT
            ID,
            'pagopar_volver_enviar',
            'sincronizacion_reseteo'
            FROM
            wp_posts
            WHERE
            post_type = 'product' ";
            $wpdb->get_results($sql_insert);
            $cantidad = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . "postmeta WHERE meta_key = 'pagopar_volver_enviar' ");
             //$resultado['resultado']="Se actualizaron ".$cantidad." registros";
            $resultado['resultado']['cantidad'] = $cantidad;
            $resultado['respuesta']=true;

        } catch (Exception $ex) {
            $resultado['respuesta'] = false;
            $resultado['resultado'] = 'Error';
        }
    } else {
        $resultado['respuesta'] = false;
        $resultado['resultado'] = 'Error de token';
    }

    header('Content-Type: application/json');
    echo json_encode($resultado);

    die();


}


if (isset($_GET['sincronizacion-pendientes'])) {
    
    include_once 'pagopar-sincronizacion.php';
    
    if (sha1($payments['pagopar']->settings['private_key'] . '') === $json_pagopar['token']) {
        try {
            
            set_time_limit(0);
            
            #$direccion_global_modificada = get_option('direccion_global_modificada');

            #if ($direccion_global_modificada==='1'){
                # Enviamos todas publicaciones que usan direccion global cuando se modifica
                #$idProductos['direccion_cambiada'] = volverEnviarProductosDireccionGlobal();
                #$idProductos['direccion_modificada'] = volverEnviarProductosDatosDependientesModificados();
                $idProductos['sin_stock'] = enviarProductosSinStock();
                $idProductos['producto_descuento'] = enviarProductosDescuento();
                $idProductos['direccion_modificada'] = volverEnviarProductosDatosDireccionModificados();
                $idProductos['direccion_global_modificada'] = volverEnviarProductosDatosDireccionGlobalModificados();
                //$idProductos['sincronizacion_reseteo'] = volverEnviarProductosDatosReSincronizacion();

            #    update_option('direccion_global_modificada', '0');
            #}
            
            
            # Enviamos publicaciones fallidas (Que ya fueraon enviadas a Pagopar pero no tuvo una respuesta satisfactoria)
            $idProductos['log_enviado_nuevamente'] = volverEnviarProductosConEnvioFallido();
            
            # Traemos las publicaciones que no se sincronzaron, es decir, que que no tienen links de pago/venta asociado
            $productosNuncaSincronizados = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "posts p WHERE p.post_type='product' and p.post_status = 'publish' AND  (select pm.meta_value from " . $wpdb->prefix . "postmeta pm where pm.post_id = p.ID and pm.meta_key = 'pagopar_link_pago_id') is null  ORDER BY ID desc ");
            
            foreach ($productosNuncaSincronizados as $key => $value) {
                $logPeticion = exportarProductoInicial($value->ID, get_post($value->ID), $update, true);;
                $idProductos['pendientes'][$value->ID] = $logPeticion;
                
            }
                    
            
            $resultado['respuesta'] = true;
            if (isset($_GET['debug'])){
                $resultado['resultado'] = $idProductos;                
            }
        } catch (Exception $ex) {
            $resultado['respuesta'] = false;
            $resultado['resultado'] = 'Error';
        }
    } else {
        $resultado['respuesta'] = false;
        $resultado['resultado'] = 'Error de token';
    }
    
    header('Content-Type: application/json');  
    echo json_encode($resultado);

    die();
    
    
}



if (isset($_GET['sincronizacion'])) {
    
    include_once 'pagopar-sincronizacion.php';
    
    if (sha1($payments['pagopar']->settings['private_key'] . '') === $json_pagopar['token']) {
        try {
            
            
            foreach ($json_pagopar['datos'] as $key => $datos) {
                
                if (in_array($datos['tipo_aviso'], array(3,4))){
                    # Creacion / edicion de producto
                    $crearEditar = importarProducto($datos);
                    $resultado['resultado'][] = $crearEditar;
                    
                }elseif (in_array($datos['tipo_aviso'], array(1))){
                    #  se actualiza stock
                    $descontarInventario = descontarInventario($datos);
                    $resultado['resultado'][] = $descontarInventario;

                }elseif (in_array($datos['tipo_aviso'], array(2))){
                    #  se actualiza stock
                    $aumentarInventario = aumentarInventario($datos);
                    $resultado['resultado'][] = $aumentarInventario;

                }
                
                
            }

            $resultado['respuesta'] = true;
        } catch (Exception $ex) {
            $resultado['respuesta'] = false;
            $resultado['resultado'] = 'Error';
        }
    } else {
        $resultado['respuesta'] = false;
        $resultado['resultado'] = 'Error de token';
    }
    
    header('Content-Type: application/json');  
    echo json_encode($resultado);

    die();
    
    
}


/**
 * Retorna los productos que se sincronizaron, los que no (y sus motivos)
 */
if (isset($_GET['resumen-sincronizacion'])) {

    if (sha1($payments['pagopar']->settings['private_key'] . 'RESUMEN') === $json_pagopar['token']) {
        try {
            $resumen = $wpdb->get_results("
                
SELECT p.ID as post_id, p.post_title, p.post_type,
(SELECT s.json_respuesta from " . $wpdb->prefix . "pagopar_sincronizacion_log_enviado s where s.post_id = p.ID order by s.log_id desc limit 1  ) as json_respuesta,
(SELECT s.json_enviar from " . $wpdb->prefix . "pagopar_sincronizacion_log_enviado s where s.post_id = p.ID order by s.log_id desc limit 1  ) as json_enviar,
(SELECT s.log_enviado from " . $wpdb->prefix . "pagopar_sincronizacion_log_enviado s where s.post_id = p.ID order by s.log_id desc limit 1  ) as log_enviado

FROM " . $wpdb->prefix . "posts p WHERE p.post_type='product' and p.post_status = 'publish' AND  (select pm.meta_value from " . $wpdb->prefix . "postmeta pm where pm.post_id = p.ID and pm.meta_key = 'pagopar_link_pago_id') is null  ORDER BY ID desc

limit 7000

");
            
            $contador = 0;
            $contadorSincronizado = 0;
            foreach ($resumen as $key => $value) {
                
                # Resumen de sincronizacion
                $arrayResumen[$contador]['estado_sincronizacion_final'] = 'No sincronizado';

                
                if ($value->log_enviado===1){
                    $arrayResumen[$contador]['log_enviado'] = 'Enviado a Pagopar';
                    $arrayResumen[$contador]['estado_sincronizacion_final'] = 'Sincronizado';
                    $contadorSincronizado = $contadorSincronizado + 1;
                }elseif ($value->log_enviado===0){
                    $arrayResumen[$contador]['log_enviado'] = 'Pendiente de envio, se enviará con el CRON de Pagopar';
                }elseif ($value->log_enviado===2){
                    $arrayResumen[$contador]['log_enviado'] = 'Enviado a Pagopar pero retornó error';
                }elseif ($value->log_enviado===3){
                    $arrayResumen[$contador]['log_enviado'] = 'Cancelado para el envio posterior';
                }else {
                    
                    $post = get_post($value->post_id);
                    
                    
                    $posibleError = exportarProductoInicial($value->post_id, $post, $update, false, true);

                    # Puesto que puede que no de error porque ya se solucionó el problema de datos, 
                    if ($posibleError['respuesta']===false){
                        $auxPosibleError = $posibleError['resultado'];
                    }else{
                        $auxPosibleError = 'Sin error encontrado, problablemente ya solucionado, en el proximo CRON se debería exportar el producto';
                    }

                    $arrayResumen[$contador]['log_enviado'] = 'No se envió a Pagopar, probablemente por error de datos iniciales: '.$auxPosibleError;
                    
                    //http://wordpress.local/?post_type=product&p=440 URL
                    
                    
                    #temp agregar datos iniciales
                }
                
                
                //1 es log enviado a Pagopar, 0 es pendiente de envio, 2 es enviado pero retorno error, 3 es cancelado para el envio
                
                
                
                $arrayResumen[$contador]['titulo'] = $value->post_title;
                $arrayResumen[$contador]['id'] = $value->post_id;
                
                $contador = $contador + 1;
            }
            

            $resultado['respuesta'] = true;
            $resultado['resultado']['productos'] = $arrayResumen;
            $resultado['resultado']['productos_total'] = $contador;
            $resultado['resultado']['productos_total_sincronizados'] = $contadorSincronizado;
        } catch (Exception $ex) {
            $resultado['respuesta'] = false;
            $resultado['resultado'] = 'Error';
        }
    } else {
        $resultado['respuesta'] = false;
        $resultado['resultado'] = 'Error de token';
    }
    header('Content-Type: application/json');  
    echo json_encode($resultado);

    die();
}

# Resumen
if (isset($_GET['resumen'])) {

    if (sha1($payments['pagopar']->settings['private_key'] . 'RESUMEN') === $json_pagopar['token']) {
        try {
            $resumen = $wpdb->get_results($wpdb->prepare("SELECT sum(1) as cantidad FROM " . $wpdb->prefix . "posts p WHERE p.post_type=%s AND p.post_status='publish' and (select pm.meta_value from wp_postmeta pm where pm.post_id = p.ID and pm.meta_key = '_virtual' and pm.meta_value = 'yes') is null  ", 'product'));

            $resultado['respuesta'] = true;
            $resultado['resultado']['cantidad_productos'] = $resumen[0]->cantidad;
        } catch (Exception $ex) {
            $resultado['respuesta'] = false;
            $resultado['resultado'] = 'Error';
        }
    } else {
        $resultado['respuesta'] = false;
        $resultado['resultado'] = 'Error de token';
    }
    header('Content-Type: application/json');  
    echo json_encode($resultado);

    die();
}

# Si coinciden los token
if (sha1($payments['pagopar']->settings['private_key'] . $json_pagopar['resultado'][0]['hash_pedido']) === $json_pagopar['resultado'][0]['token']) {
    # Marcamos como pagado en caso de que ya se haya pagado
    if (isset($order_db[0]->id)) {
        if ($json_pagopar['resultado'][0]['pagado'] === true) {

            
            $order_id = $order_db[0]->id;

            # Agregamos compatibilidad al plugin WooCommerce Sequential Order Numbers Pro, obtenemos el ID real del post
            $ordenIDReal = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM ".$wpdb->prefix."postmeta where meta_key = '_order_number' and meta_value = %s limit 1", $order_id));
            $ordenIDReal = $ordenIDReal[0];
            if (is_numeric($ordenIDReal->post_id)){
                  $order_id = $ordenIDReal->post_id;
            }			
            
            
            global $woocommerce;
            $customer_order = new WC_Order((int) $order_id);
            $nota = "Pedido Completado/Pagado." . "\n";
            $nota .= "Número de pedido en Pagopar: " . $json_pagopar['resultado'][0]['numero_pedido'] . "\n";
            $nota .= "Forma de Pago: " . $json_pagopar['resultado'][0]['forma_pago'] . "\n" . "\n";
            #$customer_order->add_order_note($nota);
            // Mark order as Paid
            
            
            $estadoPagopar = $payments['pagopar']->settings['estado_pagado_pedido_pagopar'];
            if (substr($estadoPagopar, 0, 3)==='wc-'){
                $estadoPagopar = substr($estadoPagopar, 3);
            }
            if ($estadoPagopar==''){
                $estadoPagopar = 'completed';
            }
            
            if ($estadoPagopar==='completed'){
                $customer_order->payment_complete();
            }
            
            $customer_order->update_status($estadoPagopar, $nota);
        }
    }
} else {
    echo 'Token no coincide';
    return '';
}


echo json_encode($json_pagopar['resultado']);
?>