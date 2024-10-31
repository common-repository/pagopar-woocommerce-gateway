<?php

function actualizarZonaCiudadPagopar() {

    global $wpdb;

# Traerms ciudades de Pagopar    
    $payments = WC()->payment_gateways->payment_gateways();
    $citiesConsultPagopar = new ConsultPagopar($origin_pagopar);
    $citiesConsultPagopar->publicKey = $payments['pagopar']->settings['public_key'];
    $citiesConsultPagopar->privateKey = $payments['pagopar']->settings['private_key'];
    $cities = $citiesConsultPagopar->getCities();

    $cities = json_decode(json_encode($cities), true);

    # buscamos id de la ciudad
    foreach ($cities['resultado'] as $key => $value) {
        foreach ($_POST['ciudades_ingresadas'] as $key2 => $value2) {
            if ($value2 == $value['descripcion']) {
                $idCiudad[] = $value['ciudad'];
            }
        }
    }


    # Insertamos zona de envio (Pagopar)
    $pagoparZonasEnvio = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . 'pagopar_zonas_envio' . " WHERE zona_metodo_envio_id = %s ", $_POST['id_zona_instancia']), ARRAY_A);


    # Nombre de la Zona en Woocommerce
    $WC_Shipping_Method = WC_Shipping_Zones::get_shipping_method($_POST['id_zona_instancia']);
    $myArrayShipping_Method = json_decode(json_encode($WC_Shipping_Method), true);
    $precioZona = $myArrayShipping_Method['instance_settings']['cost'];


    # SI no tiene ID de zona Pagopar asignada, insertamos en Pagopar via WS
    if (!is_numeric($pagoparZonasEnvio[0]['pagopar_zona_id'])) {
        # Insertamos la zona en Pagopar
        $zonaIdWebservice = insertar_zona_ws_pagopar($citiesConsultPagopar->publicKey, $citiesConsultPagopar->privateKey, $WC_Shipping_Method->get_title());

        if ($zonaIdWebservice['respuesta'] === true) {
            $zonaIdWebservice = $zonaIdWebservice['resultado'];
        } else {
            return false;
            die('Error al insertar la zona en Pagopar');
        }
    } else {
        $zonaIdWebservice = $pagoparZonasEnvio[0]['pagopar_zona_id'];
    }



    # Si no existe la zona Woocommerce
    if (!is_numeric($pagoparZonasEnvio[0]['id'])) {

        $data = array('pagopar_zona_id' => $zonaIdWebservice, 'tiempo_entrega' => $_POST['horas_entrega'], 'zona_metodo_envio_id' => $_POST['id_zona_instancia'], 'zona_generica' => $_POST['zona_generica']);
        $format = array('%d', '%d', '%d');
        $wpdb->insert($wpdb->prefix . 'pagopar_zonas_envio', $data, $format);
        $zonaIdInsertada = $wpdb->insert_id;
        #var_dump($zonaIdInsertada);
    } else {
        $zonaIdInsertada = $pagoparZonasEnvio[0]['pagopar_zona_id'];

        # Actualizamos en Woocommerce los datos modificados
        $wpdb->update(
                $wpdb->prefix . 'pagopar_zonas_envio', array(
            'tiempo_entrega' => $_POST['horas_entrega'],
            'zona_generica' => $_POST['zona_generica']
                ), array('zona_metodo_envio_id' => $_POST['id_zona_instancia']), array(
            '%d', // value1
            '%d'  // value2
                ), array('%d')
        );


        # Falta verificar si se cambió el precio o el tiempo y disparar el WS para actualizar
    }



    if (is_numeric($zonaIdInsertada)) {

        $table = $wpdb->prefix . 'pagopar_ciudades_x_metodos_zonas_envio';

        # Traemos las ciudades actuales de la zona
        $pagoparCiudadesZonaEnvio = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "pagopar_ciudades_x_metodos_zonas_envio where zona_metodo_envio_id = %s", $_POST['id_zona_instancia']), ARRAY_A);

        foreach ($pagoparCiudadesZonaEnvio as $key => $value) {
            $idCiudadActuales[] = $value['pagopar_ciudad_id'];
        }
        # Borramos las ciudades que se desasociaron de la zona
        $ciudadesABorrar = array_diff($idCiudadActuales, $idCiudad);
        foreach ($ciudadesABorrar as $key => $value) {

            #Borramos la asociacion ciudad/zona en woocommerce
            $wpdb->delete($table, array('pagopar_ciudad_id' => $value));

            #borramos la asociacion ciudad/zona en Pagopar via WS
            #echo 'borrar: '.$value;
            #echo '<br>';
        }

        # Agregamos las nuevas ciudades asociadas a la zona
        $ciudadesAAgregar = array_diff($idCiudad, $idCiudadActuales);
        foreach ($ciudadesAAgregar as $key => $value) {

            # Insertamos las nuevas ciudades seteadas
            $data = array('zona_metodo_envio_id' => $_POST['id_zona_instancia'], 'pagopar_ciudad_id' => $value, 'pagopar_zona_id' => $zonaIdWebservice);
            $format = array('%d', '%d', '%d');
            $wpdb->insert($table, $data, $format);
            $my_id = $wpdb->insert_id;

            # Insertamos la asociacion ciudad/zona en Pagopar via WS
            $ciudadIdWebservice = insertar_ciudad_ws_pagopar($citiesConsultPagopar->publicKey, $citiesConsultPagopar->privateKey, $zonaIdWebservice, $value, $precioZona, $_POST['horas_entrega']);

            #echo 'agregar: '.$value;
            #echo '<br>';
        }
    }

    return true;
}


/*
 * Retorna la Url de la página de Respuesta Pagopar
 */
function obtenerURLPaginaConfirmURL() {
    $page_confirm_url_pagopar = get_option('page_confirm_url_pagopar');
    $page_confirm_url_pagopar = get_permalink($page_confirm_url_pagopar);
    return $page_confirm_url_pagopar;
}

/*
 * Retorna la Url de la página de redireccionamiento
 */
function obtenerURLPaginaRedireccionamiento() {
    $page_gracias_pagopar = get_option('page_gracias_pagopar');
    //__(site_url() . "/gracias-por-su-compra/?hash=(\$hash)", 'pagopar')
    $page_gracias_pagopar = get_permalink($page_gracias_pagopar);
    if (substr($page_gracias_pagopar, -1) == '/') {
        $page_gracias_pagopar = $page_gracias_pagopar . '?hash=($hash)';
    } else {
        $page_gracias_pagopar = $page_gracias_pagopar . '/?hash=(\$hash)';
    }

    return $page_gracias_pagopar;
}

?>