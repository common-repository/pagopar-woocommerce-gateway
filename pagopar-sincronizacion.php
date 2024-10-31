<?php

/**
 * Copia imagenes provenientes de una URL y asigna a la galería del producto (sin vincular con el producto)
 * @param type $url
 * @param type $parent_post_id
 * @return boolean
 */
function copiarImagenesRemotamente($url, $parent_post_id = null) {

    if (!class_exists('WP_Http'))
        include_once( ABSPATH . WPINC . '/class-http.php' );

    $http = new WP_Http();
    $response = $http->request($url);
    if ($response['response']['code'] != 200) {
        return false;
    }

    $upload = wp_upload_bits(basename($url), null, $response['body']);
    if (!empty($upload['error'])) {
        return false;
    }

    $file_path = $upload['file'];
    $file_name = basename($file_path);
    $file_type = wp_check_filetype($file_name, null);
    $attachment_title = sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME));
    $wp_upload_dir = wp_upload_dir();

    $post_info = array(
        'guid' => $wp_upload_dir['url'] . '/' . $file_name,
        'post_mime_type' => $file_type['type'],
        'post_title' => $attachment_title,
        'post_content' => '',
        'post_status' => 'inherit',
    );

    // Create the attachment
    $attach_id = wp_insert_attachment($post_info, $file_path, $parent_post_id);

    // Include image.php
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    // Define attachment metadata
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);

    // Assign metadata to attachment
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}

/**
 * Asigna y vincula imagenes a un producto/post
 * @param type $product_id
 * @param type $image_id_array
 */
function asignarImagenesSubidasProducto($product_id, $image_id_array) {
    //take the first image in the array and set that as the featured image
    set_post_thumbnail($product_id, $image_id_array[0]);

    //if there is more than 1 image - add the rest to product gallery
    if (sizeof($image_id_array) > 1) {
        array_shift($image_id_array); //removes first item of the array (because it's been set as the featured image already)
        update_post_meta($product_id, '_product_image_gallery', implode(',', $image_id_array)); //set the images id's left over after the array shift as the gallery images
    }
}

/**
 * Descuenta la cantidad del inventario de acuerdo a una venta en otra plataforma (En Pagopar.com por ejemplo)
 * @global type $wpdb
 * @param type $json_pagopar
 */
function descontarInventario($json_pagopar) {
    global $wpdb;
    $existeProducto = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "posts p WHERE p.post_type='product' AND  (select pm.meta_value from " . $wpdb->prefix . "postmeta pm where pm.post_id = p.ID and pm.meta_key = 'pagopar_link_pago_id' and pm.meta_value = %s) is not null  ORDER BY ID desc limit 1", $json_pagopar['link_venta']));

    # Ya se exportó alguna vez el producto, se debe actualizar algunos datos
    if (is_numeric($existeProducto[0]->ID)) {

        $productoID = $existeProducto[0]->ID;

        $manage_stock = get_post_meta($productoID, '_manage_stock', true);
        $stock = get_post_meta($productoID, '_stock', true);
        $stock_status = get_post_meta($productoID, '_stock_status', true);

        $nuevoStock = intval($stock) - $json_pagopar['cantidad_venta'];

        # Actualizamos stock y estado de stock
        if ($nuevoStock > 0) {
            update_post_meta($productoID, '_stock', $nuevoStock);
            update_post_meta($productoID, '_stock_status', 'instock');
            update_post_meta($productoID, '_manage_stock', 'yes');
        } else {
            update_post_meta($productoID, '_stock', $nuevoStock);
            update_post_meta($productoID, '_stock_status', 'outofstock');
            update_post_meta($productoID, '_manage_stock', 'yes');
        }


        $resultado['link_venta'] = $json_pagopar['link_venta'];
        $resultado['logs'] = $json_pagopar['logs'];
        $resultado['tipo_aviso'] = $json_pagopar['tipo_aviso'];
        $resultado['respuesta'] = true;

        return $resultado;
    } else {
        return false;
    }
}

/**
 * Descuenta la cantidad del inventario de acuerdo a una venta en otra plataforma (En Pagopar.com por ejemplo)
 * @global type $wpdb
 * @param type $json_pagopar
 */
function aumentarInventario($json_pagopar) {
    global $wpdb;
    $existeProducto = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "posts p WHERE p.post_type='product' AND  (select pm.meta_value from " . $wpdb->prefix . "postmeta pm where pm.post_id = p.ID and pm.meta_key = 'pagopar_link_pago_id' and pm.meta_value = %s) is not null  ORDER BY ID desc limit 1", $json_pagopar['link_venta']));

    # Ya se exportó alguna vez el producto, se debe actualizar algunos datos
    if (is_numeric($existeProducto[0]->ID)) {

        $productoID = $existeProducto[0]->ID;

        $manage_stock = get_post_meta($productoID, '_manage_stock', true);
        $stock = get_post_meta($productoID, '_stock', true);
        $stock_status = get_post_meta($productoID, '_stock_status', true);

        $nuevoStock = intval($stock) + $json_pagopar['cantidad_venta'];

        # Actualizamos stock y estado de stock
        if ($nuevoStock > 0) {
            update_post_meta($productoID, '_stock', $nuevoStock);
            update_post_meta($productoID, '_stock_status', 'instock');
            update_post_meta($productoID, '_manage_stock', 'yes');
        } else {
            update_post_meta($productoID, '_stock', $nuevoStock);
            update_post_meta($productoID, '_stock_status', 'outofstock');
            update_post_meta($productoID, '_manage_stock', 'yes');
        }

        $resultado['link_venta'] = $json_pagopar['link_venta'];
        $resultado['logs'] = $json_pagopar['logs'];
        $resultado['tipo_aviso'] = $json_pagopar['tipo_aviso'];
        $resultado['respuesta'] = true;

        return $resultado;
    } else {
        return false;
    }
}

/**
 * Retorna ID de productos que utilizaron direccion global (y no la que se carga en producto), ya que si se modifica se deben exportar de nuevo dicho productos
 * @global type $wpdb
 * @return type
 */
function volverEnviarProductosDireccionGlobal() {
    global $wpdb;

    # Productos que utilizaron direccion generica
    $productosDireccionGlobal = $wpdb->get_results("SELECT post_id from " . $wpdb->prefix . "postmeta where  meta_key = 'direccion_global_utilizada' and meta_value = '1'");

    foreach ($productosDireccionGlobal as $key => $value) {
        $idProductos[$value->post_id] = exportarProductoInicial($value->post_id, get_post($value->post_id), $update, true);
    }

    return $idProductos;
}
/**
 * Envia los productos que se enviaron pero que pagopar respondió error o simplemente no respondio correctamente (Ejemplo, error de conexion, pero teniendo en cuenta
 * que pueden haber otros cambios posteriores que si se exportaron bien, en ese caso, ya no enviamos puesto que sería un envio de datos desfazados/viejos
 * @global type $wpdb
 * @return type
 */
function volverEnviarProductosConEnvioFallido() {
    global $wpdb;

    # Productos que utilizaron direccion generica $wpdb->prefix 
    $productosConEnvioFallido = $wpdb->get_results("SELECT
	le.post_id,
	( SELECT max( le2.log_id ) FROM ".$wpdb->prefix."pagopar_sincronizacion_log_enviado le2 WHERE le2.post_id = le.post_id AND log_enviado = 1 ) AS log_id_enviado_posteriormente 
FROM
	".$wpdb->prefix."pagopar_sincronizacion_log_enviado le 
WHERE
	log_enviado = 2 
	AND log_id >= ( SELECT max( le2.log_id ) FROM ".$wpdb->prefix."pagopar_sincronizacion_log_enviado le2 WHERE le2.post_id = le.post_id AND log_enviado = 1 ) 
	LIMIT 100");

    foreach ($productosConEnvioFallido as $key => $value) {
        $idProductos[$value->post_id] = exportarProductoInicial($value->post_id, get_post($value->post_id), $update, true);
    }

    return $idProductos;
}


/**
 * Importa productos (crea o edita) provenientes de Pagopar
 * @global type $wpdb
 * @param type $json_pagopar
 * @return boolean
 */
function importarProducto($json_pagopar) {
    global $wpdb;

    # Se inserta vía sql para saltar el hook de update ya que entraía en bucle infinito por la sincronizacion
    # vemos si existe el post
    $existeProducto = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "posts p WHERE p.post_type='product' AND  (select pm.meta_value from " . $wpdb->prefix . "postmeta pm where pm.post_id = p.ID and pm.meta_key = 'pagopar_link_pago_id' and pm.meta_value = %s) is not null  ORDER BY ID desc limit 1", $json_pagopar['link_venta']));

    # Ya se exportó alguna vez el producto, se debe actualizar algunos datos
    if (is_numeric($existeProducto[0]->ID)) {
        $tipoAccion = 'edicion';
        $productoID = $existeProducto[0]->ID;
    } else {
        # Aún no se exportó el producto, se debe insertar 
        # Insertamos post/publicacion
        #post_author que id poner?
        $a = $wpdb->get_results($wpdb->prepare("insert into " . $wpdb->prefix . "posts (post_title, post_content, post_status, post_type, post_date, post_date_gmt) values (%s, %s, 'publish', 'product', now(), now())", $json_pagopar['datos']['titulo'], $json_pagopar['datos']['descripcion']));
        $productoID = $wpdb->insert_id;

        # Definimos slug
        $urlAmigable = sanitize_title($json_pagopar['datos']['titulo'], $productoID);
        $a = $wpdb->get_results($wpdb->prepare("update " . $wpdb->prefix . "posts set post_name = %s where ID = %s", $urlAmigable, $productoID));

        $tipoAccion = 'creacion';
    }


    if (isset($json_pagopar['datos']['imagen'])) {

        #valores test
        #unset($json_pagopar['datos']['imagen']);
        #$json_pagopar['datos']['imagen'][] = 'https://cdn.pagopar.com/archivos/imagenes/8605bf8a5816a70b20181123221233000000.jpeg';
        #$json_pagopar['datos']['imagen'][] = 'https://www.landuum.com/wp-content/uploads/2019/03/cultura_paisajeiluminado_landuum5.jpg';
        #valores test end
        # Subimos imagenes
        foreach ($json_pagopar['datos']['imagen'] as $key => $value) {
            $imagenesSubidas[] = copiarImagenesRemotamente($value, $productoID);
        }
        $imagenesArrayMeta = $imagenesSubidas;
        sort($imagenesArrayMeta);

        # actualizamos los id de imagenes, esto se utiliza para verificar si se agregaron nuevas imagenes al editar/crear y solo en ese caso se envian esos datos a Pagopar
        update_post_meta($productoID, 'pagopar_imagenes_sincronizadas_json', json_encode($imagenesArrayMeta));

        # Asignamos al producto las imagenes subidas
        asignarImagenesSubidasProducto($productoID, $imagenesSubidas);
    }

    # Actualizamos meta del id de link de venta/pago Pagopar               
    update_post_meta($productoID, 'pagopar_link_pago_id', $json_pagopar['link_venta']);

    $precio = get_post_meta($productoID, '_price', true);
    $precioRegular = get_post_meta($productoID, '_regular_price', true);
    $precioVenta = get_post_meta($productoID, '_sale_price', true);


    #Logica de precio para mantener precio de venta con la rebaja en caso que cambie el precio y sea menor al precio regular
    #esto se hace porque link de pago de Pagopar no soporta precio_lista/precio_regular por el momento
    # Si hay oferta
    /*if (is_numeric($precioVenta)) {
        # Precio regular es menor al nuevo precio
        if ($precioRegular <= $json_pagopar['datos']['monto']) {
            if ($json_pagopar['datos']['monto'] > $precioVenta) {
                update_post_meta($productoID, '_price', $json_pagopar['datos']['monto']);
                update_post_meta($productoID, '_regular_price', $json_pagopar['datos']['monto']);
                update_post_meta($productoID, '_sale_price', '');
            } else {
                update_post_meta($productoID, '_sale_price', '');
            }
        } else {
            update_post_meta($productoID, '_sale_price', $json_pagopar['datos']['monto']);
        }
    } else {
        update_post_meta($productoID, '_price', $json_pagopar['datos']['monto']);
        update_post_meta($productoID, '_regular_price', $json_pagopar['datos']['monto']);
    }*/
    
    /*temp: testear */
  if ($json_pagopar['datos']['monto_lista']>0){
        update_post_meta($productoID, '_price', $json_pagopar['datos']['monto']);
        update_post_meta($productoID, '_regular_price', $json_pagopar['datos']['monto_lista']);
        update_post_meta($productoID, '_sale_price', $json_pagopar['datos']['monto']);

    }else{
        update_post_meta($productoID, '_price', $json_pagopar['datos']['monto']);
        update_post_meta($productoID, '_regular_price', $json_pagopar['datos']['monto']);
        update_post_meta($productoID, '_sale_price', '');
    }    
    
    
    

    update_post_meta($productoID, '_stock', $json_pagopar['datos']['cantidad']);
    if ($json_pagopar['datos']['cantidad'] > 0) {
        update_post_meta($productoID, '_stock_status', 'instock');
    } else {
        update_post_meta($productoID, '_stock_status', 'outofstock');
    }
    update_post_meta($productoID, '_manage_stock', 'yes');
    update_post_meta($productoID, '_order_stock_reduced', 'yes');

    # Para enviar a Pagopar, no se envia la categoria que se selecciona en Woocommerce, sino la que viene de Pagopar
    update_post_meta($productoID, 'pagopar_categorizacion_sincronizada', json_encode($json_pagopar['datos']['categoria']));

    # Direccion de pickup
    #update_post_meta($productoID, 'product_seller_phone', ''); // Verificar si no existe en direccion
    #update_post_meta($productoID, 'product_seller_addr', $json_pagopar['datos']['direccion']['direccion']);#ya no se usa, eliminar y probar
    #update_post_meta($productoID, 'product_seller_addr_ref', $json_pagopar['datos']['direccion']['observacion']);#ya no se usa, eliminar y probar
    #update_post_meta($productoID, 'product_seller_ciudad', $json_pagopar['datos']['direccion']['ciudad']);#ya no se usa, eliminar y probar
    #update_post_meta($productoID, 'product_seller_coo', $json_pagopar['datos']['direccion']['latitud_longitud']);#ya no se usa, eliminar y probar
    
    $direccionAsignadaProducto = crearEditatDireccion($json_pagopar['datos']['direccion']['direccion'], $json_pagopar['datos']['direccion']['ciudad'], $json_pagopar['datos']['direccion']['observacion'], $json_pagopar['datos']['direccion']['latitud_longitud'], $json_pagopar['datos']['usuario']['celular']);
    update_post_meta($productoID, 'pagopar_direccion_id_woocommerce', $direccionAsignadaProducto->id);

    if (!is_null($json_pagopar['datos']['alto'])) {
        update_post_meta($productoID, 'pagopar_sincronizacion_producto_alto', $json_pagopar['datos']['alto']);
    }
    if (!is_null($json_pagopar['datos']['peso'])) {
        update_post_meta($productoID, 'pagopar_sincronizacion_producto_peso', $json_pagopar['datos']['peso']);
    }
    if (!is_null($json_pagopar['datos']['ancho'])) {
        update_post_meta($productoID, 'pagopar_sincronizacion_producto_ancho', $json_pagopar['datos']['ancho']);
    }
    if (!is_null($json_pagopar['datos']['largo'])) {
        update_post_meta($productoID, 'pagopar_sincronizacion_producto_largo', $json_pagopar['datos']['largo']);
    }

    # AEX - Si bien no se usa en el admin (por el momento), se debe guardar para enviar los datos al exportar la publicacion
    update_post_meta($productoID, 'pagopar_envio_aex_comentario_pick_up', $json_pagopar['datos']['direccion']['comentario_pickup']);
    update_post_meta($productoID, 'pagopar_envio_aex_direccion', $json_pagopar['datos']['direccion']['direccion_retiro']);
    update_post_meta($productoID, 'pagopar_envio_aex_pickup_horario_inicio', $json_pagopar['datos']['direccion']['hora_inicio']);
    update_post_meta($productoID, 'pagopar_envio_aex_pickup_horario_fin', $json_pagopar['datos']['direccion']['hora_fin']);
    update_post_meta($productoID, 'pagopar_envio_aex_activo', $json_pagopar['datos']['envio_aex']);
    
    

    # Mobi -  Si bien no se usa en el admin (por el momento), se debe guardar para enviar los datos al exportar la publicacion
    $mobi = $json_pagopar['datos']['envio_mobi'];
    update_post_meta($productoID, 'pagopar_mobi', json_encode($mobi));

    # Envio Propio -  Si bien no se usa en el admin (por el momento), se debe guardar para enviar los datos
    update_post_meta($productoID, 'pagopar_envio_propio', json_encode($json_pagopar['datos']['envio_propio']));

    # Retiro del local -  Si bien no se usa en el admin (por el momento), se debe guardar para enviar los datos
    #update_post_meta($productoID, 'pagopar_retiro_local', json_encode($json_pagopar['datos']['retiro_local']));
    # Actualizamos en el producto
    if ($json_pagopar['datos']['retiro_local'] === true) {
        update_post_meta($productoID, 'product_enabled_retiro', 'yes');
    } else {
        update_post_meta($productoID, 'product_enabled_retiro', 'no');
    }
    update_post_meta($productoID, 'product_sucursal_obs', $json_pagopar['datos']['observacion_retiro']);

    update_post_meta($productoID, 'pagopar_id_producto', $json_pagopar['datos']['id_producto']);

    # Esto se sincroniza?
    if (($json_pagopar['datos']['activo'] === '0') or ( $json_pagopar['datos']['activo'] === false) or ( $json_pagopar['datos']['activo'] === 'false')) {
        $wpdb->get_results($wpdb->prepare("update " . $wpdb->prefix . "posts set post_status = %s where ID = %s", 'draft', $productoID));
    }

    # Armamos formato de respuesta necesaria para Pagopar
    $resultado['link_venta'] = $json_pagopar['link_venta'];
    $resultado['logs'] = $json_pagopar['logs'];
    $resultado['tipo_aviso'] = $json_pagopar['tipo_aviso'];
    $resultado['respuesta'] = true;
    $resultado['id_producto'] = $productoID;

    return $resultado;
}

function exportarProductoInicialImportacionAsincrona($object, $data){
     #print_r($object );
     #print_r($data);
     #print_r($object->get_id());
     #print_r($data['id']);
     #print_r($_POST['update_existing']);
        //die('exportarProductoInicialImportacionAsincrona');

     # Si update_existing = 1, quiere decir que se seleccionó la opcion "Actualizar productos existentes" en Productos > Importar, en este caso, ponemos que se sincronice 
     # de forma asincrona. Si no seleccionó, el webhook save_post ya llama y ya se exporta en tiempo real
     if ($_POST['update_existing']=='1'){
         update_post_meta($object->get_id(), 'pagopar_volver_enviar', 'producto_importacion_csv');
     }
     
     
}

/**
 * Inicia proceso de exportacion de producto, verificando si está apto para exportar dicho producto
 * @param type $post_id
 * @param type $post
 * @param type $update
 * @param type $tipo
 * @param type $enviarLogsPagopar
 * @return string
 */
function exportarProductoInicial($post_id, $post, $update, $enviarLogsPagopar = true, $soloRetornarError = false) {
    
    //die('exportarProductoInicial');
    # hace falta ver la logica de producto variale para pasar el id correcto
    if ($update === true) {
        // update propiedad link_pago
    }

    # SI no es un producto
    if ($post->post_type != 'product') {
        #if ($post->post_status != 'publish' || $post->post_type != 'product') {  
        $resultadoError['respuesta'] = false;
        $resultadoError['resultado'] = 'No es un producto';
        return $resultadoError;
        return;
    }


    if (!$product = wc_get_product($post)) {
        $resultadoError['respuesta'] = false;
        $resultadoError['resultado'] = 'No es un producto';
        return $resultadoError;
        return;
    }

    # Si es variable, no exportamos (por el momento)
    if (count((array)$product->get_children()) > 0) {
        $resultadoError['respuesta'] = false;
        $resultadoError['resultado'] = 'Es un producto variable, temporalmente no se sincronizan';
        return $resultadoError;
        return;
    }

    # No se procesará si es un producto virtual
    $productoVirtual = get_post_meta($post_id, '_virtual', true);
    if (strtolower($productoVirtual) === 'yes') {
        $resultadoError['respuesta'] = false;
        $resultadoError['resultado'] = 'Es un producto virtual, este tipo de producto no se sincronizan';
        return $resultadoError;
        return;
    }

    /*$precio = $product->get_price();
    if ($precio === '') {
        $precio = $product->get_regular_price();
    }*/
    
    $precioDescuento = $product->get_sale_price();
    
  # si tiene descuento, enviamos ese monto como precio/monto de venta
    if ($precioDescuento>0){
        $precio = $product->get_regular_price();
        $precioDescuento = $product->get_sale_price();
    }else{
        $precio = $product->get_regular_price();
        $precioDescuento = null;
    }    
    
   

    # Si no está en guaranies Woocommrece no se exportan los productos
    if(get_woocommerce_currency() !=='PYG'){
        $resultadoError['respuesta'] = false;
        $resultadoError['resultado'] = 'Woocommerece no está configurado en moneda Guaranies';
        return $resultadoError;
    }
    
    # No se procesa si no tiene precio
    if ($precio === '') {
        $resultadoError['respuesta'] = false;
        $resultadoError['resultado'] = 'No se ha definido precio';
        return $resultadoError;
        return;
    }
    

    $imagenesSincronizadas = get_post_meta($post_id, 'pagopar_imagenes_sincronizadas_json', true);


    # Imagen principal de la publicacion    
    if (is_numeric($product->get_image_id())) {
        if (substr(wp_get_attachment_image_url($product->get_image_id(), 'full'), 0,4)!=='http'){
            $prefijoSitio = site_url();
        }else{
            $prefijoSitio = '';
        }
        
        $imagenesArray[]['src'] = $prefijoSitio . wp_get_attachment_image_url($product->get_image_id(), 'full');
        $imagenesArrayMeta[] = intval($product->get_image_id());
    }

    # Obtenemos las imagenes de la galeria de imagenes
    $imagenesIDs = $product->get_gallery_image_ids();
    foreach ($imagenesIDs as $key => $value) {
        
        if (substr(wp_get_attachment_image_url($value, 'full'), 0,4)!=='http'){
            $prefijoSitio = site_url();
        }else{
            $prefijoSitio = '';
        }
        
        
        #if ($value!=$product->get_image_id()){
        $imagenesArray[]['src'] = $prefijoSitio.wp_get_attachment_image_url($value, 'full');
        $imagenesArrayMeta[] = $value;
        #}
    }

    # SI no tiene imagenes, no se continua con la importacion
    if (!is_array($imagenesArrayMeta)) {
        $resultadoError['respuesta'] = false;
        $resultadoError['resultado'] = 'No tiene imágenes, no se sincronizan sin imágenes';
        return $resultadoError;
    }
   
    /*if ($product->get_stock_status()==='outofstock'){
        $resultadoError['respuesta'] = false;
        $resultadoError['resultado'] = 'Producto marcado como agotado';
        return $resultadoError;
    }*/

    if ($soloRetornarError===true){
        return $resultadoError;
    }
    

    # ordenamos los id de imagenes para poder comparar y si son iguales no se incluye el parametro imagenes para no enviar a Pagopar
    sort($imagenesArrayMeta);
    if (json_encode($imagenesArrayMeta) === $imagenesSincronizadas) {
        unset($imagenesArray);
    } else {
        # actualizamos los id de imagenes puesto que se va a enviar a Pagopar
        update_post_meta($post_id, 'pagopar_imagenes_sincronizadas_json', json_encode($imagenesArrayMeta));
    }

    # temp
    #unset($imagenesArray);
    #$imagenesArray[]['src'] = 'https://assets.kogan.com/files/product/aub/lin/LIN-1950_main.jpg?auto=webp&canvas=753%2C502&fit=bounds&height=502&quality=75&width=753';
    #$imagenesArray[]['src'] = 'https://m.media-amazon.com/images/I/71aXlp2i+tL._AC_SS350_.jpg';
    # Ponemos un valor por defecto si no maneja stock
    if ($product->get_manage_stock() === false) {
        $stock = 1;
    } else {
        $stock = $product->get_stock_quantity();
    }
    
    # Si está marcado como fuera de stock, el inventario es 0
    if ($product->get_stock_status()==='outofstock'){
        $stock = 0;
    }
    
   
    
    $pagopar_link_pago_id = get_post_meta($post_id, 'pagopar_link_pago_id', true);



    if (trim($pagopar_link_pago_id) === '') {
        #echo 'Crear';
        $crearActualizarLinkPago = exportarProducto('crear', $post_id, $precio, $product->get_title(), $product->get_description(), $imagenesArray, $stock, $post, $enviarLogsPagopar, $precioDescuento);
    } else {
        #echo 'Editar';
        $crearActualizarLinkPago = exportarProducto('editar', $post_id, $precio, $product->get_title(), $product->get_description(), $imagenesArray, $stock, $post, $enviarLogsPagopar, $precioDescuento);
    }


    if ($crearActualizarLinkPago['respuesta'] === true) {
        update_post_meta($post_id, 'pagopar_link_pago_id', $crearActualizarLinkPago['resultado']['link_venta']);
        update_post_meta($post_id, 'pagopar_link_referencia', $crearActualizarLinkPago['resultado']['url_referencia']);
        update_post_meta($post_id, 'pagopar_link_referencia_texto_posterior', $crearActualizarLinkPago['resultado']['pagopar_link_referencia_texto_posterior']);
        update_post_meta($post_id, 'pagopar_link_referencia_texto_anterior', $crearActualizarLinkPago['resultado']['pagopar_link_referencia_texto_anterior']);
        //{"respuesta":true,"resultado":{"url":"https:\/\/pago.pagopar.com\/be7","id":"be7","link_venta":"14767"}}
    } else {
        # Si dio error al crear el log y ejecutar, entonces reseteamos las imagenes guardadas, sino no se enviará la siguiente vez que queramos enviar
        update_post_meta($post_id, 'pagopar_imagenes_sincronizadas_json', json_encode(null));
    }

    return $crearActualizarLinkPago;
}

/**
 * Crea el array que será insertado en log para luego enviar a Pagopar (exportar producto)
 * @param type $accion
 * @param type $idProducto
 * @param type $monto
 * @param type $titulo
 * @param type $descripcion
 * @param type $imagenesArray
 * @param type $cantidad
 * @param type $post
 * @param type $enviarLogsPagopar
 * @return type
 */
function exportarProducto($accion, $idProducto, $monto, $titulo, $descripcion, $imagenesArray, $cantidad, $post, $enviarLogsPagopar = true, $montoDescuento = null) {
    $payments = WC()->payment_gateways->payment_gateways();

    # meta datos
    $pagopar_mobi = json_decode(get_post_meta($idProducto, 'pagopar_mobi', true), true);
    #$pagopar_mobi_horarios = json_decode(get_post_meta($idProducto, 'pagopar_mobi_horarios', true), true);
    $pagopar_envio_propio = json_decode(get_post_meta($idProducto, 'pagopar_envio_propio', true), true);
    $pagopar_retiro_local = json_decode(get_post_meta($idProducto, 'pagopar_retiro_local', true), true);
    $pagopar_id_producto = get_post_meta($idProducto, 'pagopar_id_producto', true);
    $pagopar_categorizacion_sincronizada = json_decode(get_post_meta($idProducto, 'pagopar_categorizacion_sincronizada', true), true);
    ;


    if (!is_null($pagopar_mobi)) {
        $array['envio_mobi'] = $pagopar_mobi;
        $array['envio_mobi']['usuario_mobi'] = $array['envio_mobi']['mobi_usuario'];
        unset($array['envio_mobi']['mobi_usuario']);
        #Temp, averiguar que se envia cuando no esta activo
        if (is_array($array['envio_mobi'])) {
            $array['envio_mobi']['activo'] = true;
        }
        #averiguar como obtener este dato
        $array['envio_mobi'] ['direccion_retiro'] = null;
    }


    #$array['envio_mobi']['horarios'] = $pagopar_mobi_horarios;
    #$array['envio_propio'] = $pagopar_envio_propio;
    foreach ($pagopar_envio_propio as $key => $envioPropio) {
        $array['envio_propio']['zonas'][]['zona_envio'] = $envioPropio['zona_envio'];
    }
    #$array['envio_propio']['zonas'][]['zona_envio'] = 976;
    #temp averiguar de donde sale este dato
    if (is_array($array['envio_propio'])) {
        $array['envio_propio']['activo'] = true;
    }
    if (is_null($pagopar_envio_propio)) {
        unset($array['envio_propio']);
    }


    #$array['envio_propio']['zonas'][0]['zona_envio'] = $array['envio_propio']['zonas'][0]['id'];
    #$array['envio_propio']['zonas'][0]['zona_envio'] = 976;
    #$array['retiro_local'] = $pagopar_retiro_local;
    $array['id_producto'] = $pagopar_id_producto;

    $retiroHabilitado = get_post_meta($idProducto, 'product_enabled_retiro', true);
    $product_sucursal_obs = get_post_meta($idProducto, 'product_sucursal_obs', true);

    if ($retiroHabilitado === 'yes') {
        $array['retiro_local']['observacion_retiro'] = $product_sucursal_obs;
        $array['retiro_local']['activo'] = true;
    }

    # Obtenemos los datos de la direccion asociada al producto
    $direccionProducto = obtenerDireccionProducto($idProducto);
    
    
   $peso = get_post_meta($idProducto, 'pagopar_sincronizacion_producto_peso', true);
   $largo = get_post_meta($idProducto, 'pagopar_sincronizacion_producto_largo', true);
   $ancho = get_post_meta($idProducto, 'pagopar_sincronizacion_producto_ancho', true);
   $alto = get_post_meta($idProducto, 'pagopar_sincronizacion_producto_alto', true);
   $cat = $pagopar_categorizacion_sincronizada['categoria'];

   
   # Si los valores enviados por pagopar son vacios, es decir, si es la primera vez que se exporta el producto, tomamos los valores
   # de alto, largo, ancho y peso de Woocommerce
   if ((trim($peso)==='') or (trim($largo)==='') or (trim($ancho)==='') or (trim($alto)==='') or (trim($cat)==='')){
        $cat = get_post_meta($idProducto, 'pagopar_final_cat', true);       
        $peso = get_post_meta($idProducto, 'product_weight', true);
        $largo = get_post_meta($idProducto, 'pagopar_largo', true);
        $ancho = get_post_meta($idProducto, 'pagopar_ancho', true);
        $alto = get_post_meta($idProducto, 'pagopar_alto', true);

        # Reemplazamos la coma por punto
        $peso = str_replace(',','.',$peso);
        $largo = str_replace(',','.',$largo);
        $ancho = str_replace(',','.',$ancho);
        $alto = str_replace(',','.',$alto);
        
        
        
        # Si no se definieron los valores categoria, alto, largo, ancho o peso, se intenta tomar de los valores generales de woocommerce
        if ((!is_numeric($alto)) or ( !is_numeric($largo)) or ( !is_numeric($ancho)) or ( !is_numeric($peso)) or ( !is_numeric($cat))) {
            $peso = get_post_meta($idProductoPadre, '_weight', true);
            $largo = get_post_meta($idProductoPadre, '_length', true);
            $ancho = get_post_meta($idProductoPadre, '_width', true);
            $alto = get_post_meta($idProductoPadre, '_height', true);
            if (trim($cat) == '') {
                $cat = 979;
            }
        }

   }
   
   
   
   

    $array['envio_aex']['activo'] = true; //pagopar_envio_aex_activo
    $array['envio_aex']['direccion_coordenadas'] = $direccionProducto->direccion_coordenadas;
    $array['envio_aex']['peso'] = $peso;
    $array['envio_aex']['largo'] = $largo;
    $array['envio_aex']['ancho'] = $ancho;
    $array['envio_aex']['alto'] = $alto;
    $array['envio_aex']['comentarioPickUp'] = get_post_meta($idProducto, 'pagopar_envio_aex_comentario_pick_up', true);
    $array['envio_aex']['direccion_retiro'] = get_post_meta($idProducto, 'pagopar_envio_aex_direccion', true);
    $array['envio_aex']['direccion'] = $direccionProducto->direccion;
    $array['envio_aex']['direccion_ciudad'] = $direccionProducto->ciudad;
    $array['envio_aex']['hora_inicio'] = get_post_meta($idProducto, 'pagopar_envio_aex_pickup_horario_inicio', true); #verificar
    $array['envio_aex']['hora_fin'] = get_post_meta($idProducto, 'pagopar_envio_aex_pickup_horario_fin', true); #verificar
    $array['envio_aex']['direccion_referencia'] = $direccionProducto->direccion_referencia;
    #$array['envio_aex']['id'] = get_post_meta($idProducto, 'pagopar_envio_aex_id', true);
    if (!is_numeric($array['envio_aex']['id'])) {
        unset($array['envio_aex']['id']);
    }
    
    #verificar esta seccion de codigo
    $direccionGlobalUtilizada = false;

    # Si está vacio los campos de direccion del producto, entonces quitamos de dirección global    
    if (trim($array['envio_aex']['direccion_coordenadas']) === '') {
        $direccionGlobalUtilizada = true;
        $array['envio_aex']['direccion_coordenadas'] = $payments['pagopar']->settings['seller_coo'];
    }

    if (trim($array['envio_aex']['comentarioPickUp']) === '') {
        $direccionGlobalUtilizada = true;
        $array['envio_aex']['comentarioPickUp'] = $payments['pagopar']->settings['pagopar_envio_aex_comentario_pick_up'];
    }

    if (trim($array['envio_aex']['comentarioPickUp']) === '') {
        $direccionGlobalUtilizada = true;
        $array['envio_aex']['comentarioPickUp'] = $payments['pagopar']->settings['pagopar_envio_aex_comentario_pick_up'];
    }

    if (trim($array['envio_aex']['direccion_ciudad']) === '') {
        $direccionGlobalUtilizada = true;
        $array['envio_aex']['direccion_ciudad'] = $payments['pagopar']->settings['seller_ciudad'];
    }

    if (trim($array['envio_aex']['hora_inicio']) === '') {
        $direccionGlobalUtilizada = true;
        $array['envio_aex']['hora_inicio'] = $payments['pagopar']->settings['pagopar_envio_aex_pickup_horario_inicio'];
    }

    if (trim($array['envio_aex']['hora_fin'] === '')) {
        $direccionGlobalUtilizada = true;
        $array['envio_aex']['hora_fin'] = $payments['pagopar']->settings['pagopar_envio_aex_pickup_horario_fin'];
    }


    if (trim($array['envio_aex']['direccion_retiro']) === '') {
        //$direccionGlobalUtilizada = true;
        $array['envio_aex']['direccion_retiro'] = null;
    }


    if (trim($array['envio_aex']['direccion']) === '') {
        $direccionGlobalUtilizada = true;
        $array['envio_aex']['direccion'] = $payments['pagopar']->settings['seller_addr'];
    }

    if (trim($array['envio_aex']['direccion_referencia']) === '') {
        $direccionGlobalUtilizada = true;
        $array['envio_aex']['direccion_referencia'] = $payments['pagopar']->settings['seller_addr_ref'];
    }


    # Guardamos ya que si se está utilizando la direccion global, en caso de modificarse esta, se deben actualizar estas publicaciones
    if ($direccionGlobalUtilizada === true) {
        update_post_meta($idProducto, 'direccion_global_utilizada', '1');
    } else {
        update_post_meta($idProducto, 'direccion_global_utilizada', '0');
    }

    $array['categoria'] = $cat;


    $array['token_publico'] = $payments['pagopar']->settings['public_key'];
    $array['token'] = sha1($payments['pagopar']->settings['private_key'] . 'LINKS-VENTA');

    # Enviamos por defecto producto generico con aex con datos de demensiones por defecto
    if ((trim($array['categoria']) == '') or ( trim($array['envio_aex']['peso']) == '') or ( trim($array['envio_aex']['alto']) == '') or ( trim($array['envio_aex']['ancho']) == '') or ( trim($array['envio_aex']['largo']) == '')) {
        $array['categoria'] = 979;
        $array['envio_aex']['peso'] = 1;
        $array['envio_aex']['alto'] = 1;
        $array['envio_aex']['ancho'] = 1;
        $array['envio_aex']['largo'] = 1;
    }

    $array['link_publico'] = true;

    if ($post->post_status === 'publish') {
        $array['activo'] = true;
    } else {
        # si es draft o pendiente de revision
        $array['activo'] = false;
    }

    $array['link_venta'] = get_post_meta($idProducto, 'pagopar_link_pago_id', true);
    ;

    # si tiene descuento, enviamos ese monto como precio/monto de venta
    if ($montoDescuento>0){
        $array['monto'] = $montoDescuento;
        $array['monto_lista'] = $monto;
    }else{
        $array['monto'] = $monto;        
    }

    $array['id_producto'] = $idProducto;
    $array['titulo'] = $titulo;
    $array['descripcion'] = $descripcion;
    $array['cantidad'] = $cantidad;

    foreach ($imagenesArray as $key => $value) {
        $array['imagen'][] = $value['src'];
    }

    if ($accion == 'crear') {
        $agregarLog = agregarLog($array, 'crear', $idProducto);
    } else {
        $agregarLog = agregarLog($array, 'editar', $idProducto);
    }

    # Procesamos inmediatamente, si se puede
    if ($enviarLogsPagopar === true) {
        $procesarLogByLogID = procesarLogByLogID($agregarLog, $idProducto);
    }

    return $procesarLogByLogID;
}

/**
 * Agrega en el log el array de producto para exportar
 * @global type $wpdb
 * @param type $array
 * @param type $accion
 * @param type $idProducto
 * @return type
 */
function agregarLog($array, $accion, $idProducto) {
    global $wpdb;

    if ($accion === 'editar') {
        $accion = 2;
    } else {
        $accion = 1;
    }

    # Insertamos para que luego se procese
    $wpdb->get_results($wpdb->prepare("insert into " . $wpdb->prefix . "pagopar_sincronizacion_log_enviado "
                    . "(json_enviar, log_enviado, post_id, accion ) values(%s, %s, %s, %s) ", json_encode($array), 0, $idProducto, $accion));

    return $wpdb->insert_id;
}

/**
 * Envia a Pagopar el producto
 * @global type $wpdb
 * @param type $logID
 * @param type $idProducto
 * @return type
 */
function procesarLogByLogID($logID, $idProducto) {
    global $wpdb;

    # preguntar si existe un log pendiente de procesar, si es asi, no se procesa, y se procesará por procesamiento masivo, para que no se pise, 
    # si no existe un log pendiente de procesar de ese post, entonces si procesamos inmediatamente
    # Vemos si existen logs del mismo producto que dieron error, por tanto ponemos como cancelado
    $existeLogPendienteProducto = $wpdb->get_results($wpdb->prepare("select group_concat(log_id) as log_id from  " . $wpdb->prefix . "pagopar_sincronizacion_log_enviado where log_enviado in(0,2)  and  post_id = %s", $idProducto));
    if (trim($existeLogPendienteProducto[0]->log_id) !== '') {
        $wpdb->get_results("update " . $wpdb->prefix . "pagopar_sincronizacion_log_enviado set log_enviado = 3 where log_id in (" . $existeLogPendienteProducto[0]->log_id . ") ");
    }

    # Traemos el array a enviar
    $arrayAEnviar = $wpdb->get_results($wpdb->prepare("select * from  " . $wpdb->prefix . "pagopar_sincronizacion_log_enviado where log_id = %s", $logID));
    #var_dump($arrayAEnviar[0]->json_enviar);
    # Enviamos a Pagopar los datos
    $procesarLogID = procesarLogRemotamente($arrayAEnviar[0]->json_enviar, $arrayAEnviar[0]->accion);
    $procesarLogIDArray = json_decode($procesarLogID, true);

    if ($procesarLogIDArray['respuesta']) {
        $logEnviadoOK = 1;
    } else {
        $logEnviadoOK = 2; #error        
    }
    # Guardamos la respuesta en la base de datos
    $wpdb->get_results($wpdb->prepare("update " . $wpdb->prefix . "pagopar_sincronizacion_log_enviado "
                    . " set json_respuesta = %s, log_enviado = %s, post_id = %s, log_enviado = %s  where log_id = %s ", $procesarLogID, $logEnviadoOK, $idProducto, $logEnviadoOK, $logID));

    //var_dump($procesarLogIDArray);die('aaa');
    return $procesarLogIDArray;
}

/**
 * Enviamos los datos del producto a Pagopar
 * @param type $array
 * @param type $accion
 * @return type
 */
function procesarLogRemotamente($array, $accion) {
    if ($accion == '2') {
        $url = 'https://api-plugins.pagopar.com/api/links-venta/1.1/editar/';
    } else {
        $url = 'https://api-plugins.pagopar.com/api/links-venta/1.1/agregar/';
    }

    return runCurl($array, $url, 'POST', false);
}

/**
 * Ejecuta petición CURL
 * @param type $args
 * @param type $url
 * @param type $metodo
 * @param type $aplicarJsonEncode
 * @return type
 */
function runCurl($args, $url, $metodo = 'POST', $aplicarJsonEncode = true) {
    if ($aplicarJsonEncode === true) {
        $args = json_encode($args);
    }


    $ch = curl_init();
    $headers = array('Accept: application/json', 'Content-Type: application/json', 'X-Origin: ' . 'Woocommerce');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
    } elseif ($metodo === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo); //GET - DELETE        
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);


    return $response;
}

/**
 * Exporta los producto al momento de realizar una compra
 * @param type $order
 */
function avisarInventarioCambiadoCompra($order) {
    $items = $order->get_items();
    $items_ids = array();
    foreach ($items as $item) {
        $items_ids[] = $item['product_id'];

        # Obtenemos el post del producto
        $post = get_post($item['product_id']);

        $debug[] = exportarProductoInicial($item['product_id'], $post, $update);
    }
}

/**
 * Agrega texto de url de referencia en descripcion
 * @global type $product
 * @param type $content
 * @return string
 */
function woocommerceDescripcionModificada( $content ) {
    return $content;
    // Solo si es producto

    $myfile = fopen("forma_pago.txt", "w");
    $txt = 2;
    fwrite($myfile, $txt);
    fclose($myfile);


    if ( is_product() ) {
        global $product;
        $pagoparLinkReferencia = get_post_meta($product->get_id(), 'pagopar_link_referencia', true);
        $pagoparLinkReferenciaTextoPosterior = get_post_meta($product->get_id(), 'pagopar_link_referencia_texto_posterior', true);
        $pagoparLinkReferenciaTextoAnterior = get_post_meta($product->get_id(), 'pagopar_link_referencia_texto_anterior', true);
        
        
        if (trim($pagoparLinkReferencia)!==''){
            $custom_content = '<p>'.$pagoparLinkReferenciaTextoAnterior. '<a href="'.$pagoparLinkReferencia.'">'.$product->get_name().'</a> '.$pagoparLinkReferenciaTextoPosterior.'</p>';
            $content .= $custom_content;            
        }

    }
    return $content;
}

/**
 * marca como volver a enviar las publicaciones que tenian asignada una dirección que fue modificada
 * @global type $wpdb
 * @param type $idDireccion
 */
function ponerVolverEnviarDireccionModificada($idDireccion) {
    global $wpdb;
    $productosAfectados = $wpdb->get_results($wpdb->prepare("SELECT p.ID FROM " . $wpdb->prefix . "posts  p WHERE p.post_type='product' AND  (select pm.meta_value from " . $wpdb->prefix . "postmeta pm where pm.post_id = p.ID and pm.meta_key = 'pagopar_direccion_id_woocommerce' and pm.meta_value = %s) is not null  ORDER BY ID desc", $idDireccion));
    
    foreach ($productosAfectados as $key => $value) {
        update_post_meta($value->ID, 'pagopar_volver_enviar', 'direccion_modificada'); 
    }
    
}

/**
 * Marca como volver a enviar todas las publicaciones que tienen asignada una direccion distanta a la nueva direccion global
 * @global type $wpdb
 * @param type $idDireccion
 */
function ponerVolverEnviarDireccionGlobalModificada($idDireccion) {
    global $wpdb;
    # Traemos todas los productos que tienen asignado una direccion distinta a la direccion global
    $productosAfectados = $wpdb->get_results($wpdb->prepare("SELECT p.ID as post_id FROM " . $wpdb->prefix . "posts  p WHERE p.post_type='product' AND  (select pm.meta_value from " . $wpdb->prefix . "postmeta pm where pm.post_id = p.ID and pm.meta_key = 'pagopar_direccion_id_woocommerce' and pm.meta_value <> %s) is not null  ORDER BY ID desc", $idDireccion));
    
    foreach ($productosAfectados as $key => $value) {
        update_post_meta($value->post_id, 'pagopar_volver_enviar', 'direccion_global_modificada'); 
        update_post_meta($value->post_id, 'pagopar_direccion_id_woocommerce', $idDireccion); 
    }
    
}

/**
 * Envia a Pagopar productos que ciertos datos externos fueron modificados, ejemplo, la calle de la direccion_id a la que está asociada
 * @global type $wpdb
 * @return type
 */
function volverEnviarProductosDatosDireccionModificados() {
    global $wpdb;

    # Productos que utilizaron direccion generica
    $productosDireccionGlobal = $wpdb->get_results("SELECT post_id, meta_value from " . $wpdb->prefix . "postmeta where  meta_key = 'pagopar_volver_enviar' and meta_value = 'direccion_modificada' ORDER BY post_id asc");
    
    # Posibles valores de pagopar_volver_enviar: direccion_modificada, direccion_global_modificada
    
    foreach ($productosDireccionGlobal as $key => $value) {
        $idProductos[$value->post_id] = exportarProductoInicial($value->post_id, get_post($value->post_id), $update, true);
        update_post_meta($value->post_id, 'pagopar_volver_enviar', ''); 
    }

    return $idProductos;
}

/**
 * @desc Exporta productos que estan marcados como sin stock por unica vez a  fin de que se solucione el problema de sincronizacion existente
 * @global type $wpdb
 * @return type
 */
function enviarProductosSinStock() {
    global $wpdb;

    # Productos que utilizaron direccion generica
    $productosDireccionGlobal = $wpdb->get_results("SELECT
	pm.post_id as post_id,
	pm.meta_value 
    FROM
        " . $wpdb->prefix . "postmeta pm 
    WHERE
        pm.meta_key = '_stock_status' 
        AND pm.meta_value = 'outofstock' 
        AND ( SELECT 1 FROM " . $wpdb->prefix . "postmeta pm2 WHERE pm2.meta_key = 'pagopar_producto_sin_stock_importado' AND pm2.meta_value = '1' AND pm2.post_id = pm.post_id) IS NULL 
    ORDER BY
        pm.post_id desc limit 100");
    
    # Asignamos un valor a pagopar_producto_sin_stock_importado para que no se vuelva a enviar
    
    foreach ($productosDireccionGlobal as $key => $value) {
        $idProductos[$value->post_id] = exportarProductoInicial($value->post_id, get_post($value->post_id), $update, true);
        update_post_meta($value->post_id, 'pagopar_producto_sin_stock_importado', '1'); 
    }
    
    return $idProductos;
}



/**
 * @desc Exporta productos que tienen precios con descuento, ya que antes solo se exportaba el menor precio como precio de venta
 * @global type $wpdb
 * @return type
 */
function enviarProductosDescuento() {
    global $wpdb;

    # Productos que utilizaron direccion generica
    $productosDireccionGlobal = $wpdb->get_results("SELECT
	pm.post_id,
	pm.meta_value 
    FROM
	" . $wpdb->prefix . "postmeta pm 
    WHERE
	pm.meta_key = '_sale_price' 
	AND pm.meta_value > 0 
	AND ( SELECT 1 FROM " . $wpdb->prefix . "postmeta pm2 WHERE pm2.meta_key = 'pagopar_producto_descuento_importado' AND pm2.meta_value = '1' AND pm2.post_id = pm.post_id limit 1) IS NULL 
    ORDER BY
	pm.post_id desc limit 100");
    
    # Asignamos un valor a pagopar_producto_sin_stock_importado para que no se vuelva a enviar
    
    foreach ($productosDireccionGlobal as $key => $value) {
        $idProductos[$value->post_id] = exportarProductoInicial($value->post_id, get_post($value->post_id), $update, true);
        update_post_meta($value->post_id, 'pagopar_producto_descuento_importado', '1'); 
    }

    return $idProductos;
}

function volverEnviarProductosDatosDireccionGlobalModificados() {
    global $wpdb;

    # Productos que utilizaron direccion generica
    $productosDireccionGlobal = $wpdb->get_results("SELECT post_id, meta_value from " . $wpdb->prefix . "postmeta where  meta_key = 'pagopar_volver_enviar' and meta_value = 'direccion_global_modificada' ORDER BY post_id asc");
    
    # Posibles valores de pagopar_volver_enviar: direccion_modificada, direccion_global_modificada
    
    foreach ($productosDireccionGlobal as $key => $value) {
        $idProductos[$value->post_id] = exportarProductoInicial($value->post_id, get_post($value->post_id), $update, true);
        update_post_meta($value->post_id, 'pagopar_volver_enviar', ''); 
    }

    return $idProductos;
}




?>