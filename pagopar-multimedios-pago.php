<?php

function catastroInicial($contratoFirmado = false) {

    $urlBasePlugin = plugin_dir_url(__FILE__);

    if ($contratoFirmado === false) {
        return false;
    }

    # Obtenemos las tarjetas guardadas por el usuario
    $pp_cards = pp_obtener_lista_tarjetas();

    $responseDecode = json_decode($pp_cards);
    $pp_list_cards = $responseDecode->resultado;


    $imagenPrincipal = array(
        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/visa.png'),
        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/mc.png'),
        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/aex.png'),
        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/cabal.png')
    );

    $botonAgregarTarjeta = '';
    $contador = 1;
    foreach ($pp_list_cards as $key => $item) {
        

        $marcaTarjeta = '';
        $imagenTarjeta = array(array());
        if (strpos($item->url_logo, 'visa') !== false) {
            $marcaTarjeta = 'VISA - ';
            $imagenTarjeta = array(
                array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/visa.png')
            );
        } elseif (strpos($item->url_logo, 'mastercard') !== false) {
            $marcaTarjeta = 'Mastercard - ';
            $imagenTarjeta = array(
                array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/mc.png')
            );
        }


        $formasPagosDisponiblesAgrupados[$contador]['forma_pago'] = $item->alias_token;
        $formasPagosDisponiblesAgrupados[$contador]['imagen'] = $imagenTarjeta;
        $formasPagosDisponiblesAgrupados[$contador]['imagen_principal'] = $imagenPrincipal;
        $formasPagosDisponiblesAgrupados[$contador]['titulo'] = $marcaTarjeta . $item->tarjeta_numero;

        $contador = $contador + 1;
    }


    $imagen = array(
        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/mc.png')
    );




    # Boton "Agregar tarjeta"
    $formasPagosDisponiblesAgrupados[0]['forma_pago'] = 16;
    $formasPagosDisponiblesAgrupados[0]['imagen'] = array(array());
    $formasPagosDisponiblesAgrupados[0]['imagen_principal'] = $imagenPrincipal;
    $formasPagosDisponiblesAgrupados[0]['titulo'] = 'Agregar tarjeta';
    $formasPagosDisponiblesAgrupados[0]['tipo'] = 'botonAgregarTarjeta';





    return $formasPagosDisponiblesAgrupados;
}

function obtenerMediosPagosWS($retornarArrayRespuesta=false) {

    $payments = WC()->payment_gateways->payment_gateways();


    $datos['token_publico'] = $payments['pagopar']->settings['public_key'];
    $datos['token'] = sha1($payments['pagopar']->settings['private_key'] . 'FORMA-PAGO');

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
        CURLOPT_POSTFIELDS => json_encode($datos),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => array(
            "Cache-Control: no-cache",
            "Content-Type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if($retornarArrayRespuesta===true){
        return json_decode($response, true);
    }
    
    if ($err) {
        $respuesta['respuesta'] = false;
        $respuesta['resultado'] = $err;
    } else {
        $respuesta['respuesta'] = true;
        $respuesta['resultado'] = $response;
    }
  

    return $respuesta;
}

/**
 * Obtiene detos del comercios, formas de de pagos habilitados de la base de datos o directamente de Pagopar según el tiempo de la ultima ejecución
 */
function obtenerDatosComerciosWsUpdate($publicKey, $privateKey) {

    $datosComercio = get_option('pagopar_datos_comercios_json');
    $datosFormasPagosHabilitados = get_option('pagopar_formas_pagos_habilitados_json');
    
    
    $fechaDatosComerciosActualizacion = get_option('pagopar_fecha_datos_comercios_actualizacion');
    $errorEjecutandoWS = false;

    
    /*print_r($datosComercio);
    print_r($fechaDatosComerciosActualizacion);
    print_r(@date('Y-m-d H:i:s'));
    die();*/
    
      
    if (($datosComercio === false) || ($fechaDatosComerciosActualizacion === false)) {

        $datosComercioWs = traerDatosComercio();
        $datosComercioWs = json_decode($datosComercioWs, true);

        if ($datosComercioWs['respuesta'] === true) {

            $formasPagoWS = obtenerMediosPagosWS();

            # Verificamos que el endpoint de formas de pagos no de error, sino, se retornará desde la db
            if ($formasPagoWS['respuesta'] === true) {
                $formasPagoWSArray = json_decode($formasPagoWS['resultado'], true);
                if ($formasPagoWSArray['respuesta'] !== true) {
                    $errorEjecutandoWS = true;
                } else {
                    $formasPagoHabilitados = $formasPagoWSArray['resultado'];
                }
            }

            $datosComercioGuardar['contrato_firmado'] = $datosComercioWs['resultado']['contrato_firmado'];


            $formasPagoFechaActualizacion = @date('Y-m-d H:i:s');

            # Actualizamos
            if ($errorEjecutandoWS === false) {
                update_option('pagopar_formas_pagos_habilitados_json', json_encode($formasPagoHabilitados));
                update_option('pagopar_datos_comercios_json', json_encode($datosComercioGuardar));
                update_option('pagopar_fecha_datos_comercios_actualizacion', $formasPagoFechaActualizacion);
            }

            # datos a retornar
            $datosComercioResultado['datos_comercios_array'] = json_decode($datosComercio, true);
            $datosComercioResultado['formas_pagos_habilitados_array'] = json_decode($datosFormasPagosHabilitados, true);
            $datosComercioResultado['fecha_actualizacion'] = $fechaDatosComerciosActualizacion;
        }
    } else {

        $fechaActual = new DateTime(@date('Y-m-d H:i:s'));
        $fechaUltimaActualizacion = new DateTime($fechaDatosComerciosActualizacion);
        $diff = $fechaActual->diff($fechaUltimaActualizacion);
        
        $fechaActual =  $fechaActual->format('Y-m-d H:i:s');
        
        
       
        # hallamos la diferencia de fechas entre en minutos
        $minutosDiferencia = (strtotime($fechaActual)-strtotime($fechaDatosComerciosActualizacion))/60;
        $minutosDiferencia = abs($minutosDiferencia); $minutosDiferencia = floor($minutosDiferencia);
        
     

        # Si pasó más de 3 horas, volvemos a ejecutar, si falla, retornamos lo cacheado via DB
        if ($minutosDiferencia > 1440) {
            $datosComercioWs = traerDatosComercio();
            $datosComercioWs = json_decode($datosComercioWs, true);

            if ($datosComercioWs['respuesta'] === true) {

                $formasPagoWS = obtenerMediosPagosWS();
                
            

                # Verificamos que el endpoint de formas de pagos no de error, sino, se retornará desde la db
                if ($formasPagoWS['respuesta'] === true) {
                    $formasPagoWSArray = json_decode($formasPagoWS['resultado'], true);
                    if ($formasPagoWSArray['respuesta'] !== true) {
                        $errorEjecutandoWS = true;
                    } else {
                        $formasPagoHabilitados = $formasPagoWSArray['resultado'];
                    }
                }

                $datosComercioGuardar['contrato_firmado'] = $datosComercioWs['resultado']['contrato_firmado'];


                $formasPagoFechaActualizacion = @date('Y-m-d H:i:s');

                # Actualizamos
                if ($errorEjecutandoWS === false) {
                    update_option('pagopar_formas_pagos_habilitados_json', json_encode($formasPagoHabilitados));
                    update_option('pagopar_datos_comercios_json', json_encode($datosComercioGuardar));
                    update_option('pagopar_fecha_datos_comercios_actualizacion', $formasPagoFechaActualizacion);
                }

                # datos a retornar
                $datosComercioResultado['datos_comercios_array'] = json_decode($datosComercio, true);
                $datosComercioResultado['formas_pagos_habilitados_array'] = json_decode($datosFormasPagosHabilitados, true);
                $datosComercioResultado['fecha_actualizacion'] = $fechaDatosComerciosActualizacion;
            }
        } else {
            
            


            $formasPagoJson = $payments['pagopar']->settings['json_forma_pago'];
            $formasPagoFechaActualizacion = $payments['pagopar']->settings['json_forma_pago_fecha_actualizacion'];

            # datos a retornar
            $datosComercioResultado['datos_comercios_array'] = json_decode($datosComercio, true);
            $datosComercioResultado['formas_pagos_habilitados_array'] = json_decode($datosFormasPagosHabilitados, true);
            $datosComercioResultado['fecha_actualizacion'] = $fechaDatosComerciosActualizacion;
            
            
            
            
        }
    }
    
    # Partem temporal, para eliminar pago movil e infonet cobranzas
    $bkMediosPagos = $datosComercioResultado['formas_pagos_habilitados_array'];
    unset($datosComercioResultado['formas_pagos_habilitados_array']);
    foreach ($bkMediosPagos as $key => $value) {
        if (($value['forma_pago']==13) or ($value['forma_pago']==15)){
        }else{
            $newFormasPago[] = $bkMediosPagos[$key];
        }
    }
    $datosComercioResultado['formas_pagos_habilitados_array'] = $newFormasPago;
    


    return $datosComercioResultado;
}


/*
 * Duplica la clase de Pagopar para agregar más medios de pagos agrupados por categoria: Tarjetas, Efectivo, Billteras
 */

function aplicar_multimedios_pagos($allowed_gateways) {
    #return $allowed_gateways;
    $urlBasePlugin = plugin_dir_url(__FILE__);
    

    if (!empty($allowed_gateways['pagopar'])) {

        # Obtenemos todos los medios de pagos habilitados para luego realizar una o varias copias
        $all_gateways = WC()->payment_gateways->payment_gateways();

        # Obtenemos datos del comercio y formas de pago desde db o Ws
        $datosComerciosUpdate = obtenerDatosComerciosWsUpdate($all_gateways['pagopar']->settings['public_key'], $all_gateways['pagopar']->settings['private_key']);
        

        $formasPagosDisponibles = $datosComerciosUpdate['formas_pagos_habilitados_array'];
        if (count((array)$formasPagosDisponibles) > 1) {
            
            $existeUpay = false;
            foreach ($formasPagosDisponibles as $key => $value) {
                if ($value['forma_pago']==26){
                    $existeUpay = true;
                    break;
                }
            }


            # Agrupamos por categoria de medio de pago
            $contador = 0;
            foreach ($formasPagosDisponibles as $key => $value) {


                switch ($value['forma_pago']) {
                    case 1:
                        $imagen = array(
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/mc.png'),
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/visa.png'),
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/credicard.png'),
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/unica.png')
                        );
                        break;
                    case 26:
                        $imagen = array(
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/mc.png'),
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/visa.png'),
                        );
                        break;

                    case 9:
                    $imagen = array(
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/aex.png'),
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/cabal.png'),
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/panal.png'),
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/credifielco.png')
                    );
                    break;
    
    
                    case 13:
                        $imagen = array(
                            array('class' => '', 'url' => null)
                        );
                        break;

                    case 22:
                        $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/wepa.png'));
                        break;
                    case 2:
                        $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/aquipago.png'));
                        break;
                    case 3:
                        $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/pagoexpress.png'));
                        break;
                    case 4:
                        $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/practipago.png'));
                        break;

                    case 10:
                        $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/tigomoney.png'));
                        break;

                    case 23:
                        $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/pago-giros-claro.png'));
                        break;
                    case 25:
                        $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/pix.png'));
                        break;
                    
                    case 12:
                        $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/billeterapersonal.png'));
                        break;
                    case 18:
                        $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/zimple.png'));
                        break;
                        case 20:
                    $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/wally.png'));
                    break;  
                    case 26:
                        $imagen = array(array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/upay.png'));
                        break;


                    default:
                        $imagen = null;
                        break;
                }

                switch ($value['forma_pago']) {
                    case 1:
                        $titulo = 'Tarjetas internacionales con Procard (Visa y MC emitidas en el exterior)';
                        break;
                    case 9:
                        $titulo = 'Tarjetas Cabal, Panal, Maestro, Bancard Check, Credicard, American Express, Credifielco, Única, Union Pay, JCB, Discover, Diners Club Internacional.';
                        break;
                    case 16:
                        $titulo = 'Tarjetas Cabal, Panal, Maestro, Bancard Check, Credicard, American Express, Credifielco, Única, Union Pay, JCB, Discover, Diners Club Internacional.';
                        break;
                    case 26:
                        $titulo = 'Tarjetas VISA y Mastercard';
                        break;
                    case 2:
                        $titulo = $value['titulo'];
                        break;
                    case 3:
                        $titulo = $value['titulo'];
                        break;
                    case 4:
                        $titulo = $value['titulo'];
                        break;

                    default:
                        $titulo = $value['titulo'];
                        break;
                }

                if (in_array($value['forma_pago'], array(22, 2, 3, 4, 15))) {
                    $agrupacion = 'efectivo';
                    $descripcionPrincipal = 'Acercándose a las bocas de pagos habilitadas';
                    $imagenPrincipal = array(
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/wepa.png'),
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/aquipago.png'),
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/pagoexpress.png'),
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/practipago.png')
                    );
                } elseif (in_array($value['forma_pago'], array(1, 9, 16, 13))) {
                    $agrupacion = 'tarjetas';
                    
                    if ($existeUpay===false){
                        $descripcionPrincipal = 'Tarjetas VISA, Mastercard, Cabal, Panal, Maestro, Bancard Check, Credicard, American Express, Credifielco, Única, Union Pay, JCB, Discover, Diners Club Internacional.';
                        $imagenPrincipal = array(
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/visa.png'),
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/mc.png'),
                            array('class' => 'hidden_method', 'url' => $urlBasePlugin . 'images/medios-pagos/aex.png'),
                            array('class' => 'hidden_method', 'url' => $urlBasePlugin . 'images/medios-pagos/cabal.png'),
                            array('class' => 'hidden_method', 'url' => $urlBasePlugin . 'images/medios-pagos/panal.png')
                        );                        
                    }else{
                        $descripcionPrincipal = 'Tarjetas Cabal, Panal, Maestro, Bancard Check, Credicard, American Express, Credifielco, Única, Union Pay, JCB, Discover, Diners Club Internacional.';
                        $imagenPrincipal = array(
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/aex.png'),
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/cabal.png'),
                            array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/panal.png')
                        );
                        
                    }
                    
                } elseif (in_array($value['forma_pago'], array(26))) {
                    $agrupacion = 'upay';
                    $descripcionPrincipal = 'Pagá con tus tarjetas VISA y Mastercard nacionales e internacionales.';
                    $imagenPrincipal = array(
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/visa.png'),
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/mc.png'),
                    );
                } elseif (in_array($value['forma_pago'], array(18, 10, 12, 20))) {
                    $agrupacion = 'billeteras';
                    $descripcionPrincipal = 'Utilizá los fondos de tu billetera';
                    $imagenPrincipal = array(
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/tigomoney.png'),
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/billeterapersonal.png'),
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/pago-giros-claro.png'),                        
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/zimple.png'),
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/wally.png')
                    );
                } elseif (in_array($value['forma_pago'], array(11))) {
                    $agrupacion = 'transferencia_bancaria';
                    $descripcionPrincipal = 'Realizá una transferencia desde tu cuenta bancaria. Los pagos se procesan los días hábiles de 08:30 a 17:30 hs. Fuera de este horario, el pago se concretará el día siguiente hábil.';
                    $imagenPrincipal = null;
                } elseif (in_array($value['forma_pago'], array(25))) {
                    $agrupacion = 'pix';
                    $descripcionPrincipal = 'Pagá desde tu cuenta bancaria de Brasil a través de PIX.';
                    $imagenPrincipal = null;
                }  elseif (in_array($value['forma_pago'], array(24))) {
                    $agrupacion = 'bancard_qr';
                    $descripcionPrincipal = 'QR con la APP de tu banco, financiera o cooperativa.';
                    $imagenPrincipal = null;
                    /*$imagenPrincipal = array(
                        array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/pago-qr-app.png')
                    );  */                  
                }
                

                

                $formasPagosDisponiblesAgrupados[$agrupacion][$contador]['forma_pago'] = $value['forma_pago'];
                $formasPagosDisponiblesAgrupados[$agrupacion][$contador]['imagen'] = $imagen;
                $formasPagosDisponiblesAgrupados[$agrupacion][$contador]['imagen_principal'] = $imagenPrincipal;
                $formasPagosDisponiblesAgrupados[$agrupacion][$contador]['titulo'] = $titulo;

                $formasPagosDisponiblesAgrupadosCabecera[$agrupacion][$contador]['titulo'] = $titulo;
                $formasPagosDisponiblesAgrupadosCabecera[$agrupacion][$contador]['imagen_principal'] = $imagenPrincipal;
                $formasPagosDisponiblesAgrupadosCabecera[$agrupacion][$contador]['descripcion_principal'] = $descripcionPrincipal;


                $contador = $contador + 1;
            }


            # Clonamos objeto Pagopar según categoria si existe algun medio de pago final en dicha categoria    

            # Si tiene contrato formado
            if (($datosComerciosUpdate['datos_comercios_array']['contrato_firmado']===true) and (is_user_logged_in())) {

                $itemsCatastro = catastroInicial($datosComerciosUpdate['datos_comercios_array']['contrato_firmado']);

                $imagenPrincipal = array(
                    array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/visa.png'),
                    array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/mc.png'),
                    array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/aex.png'),
                    array('class' => '', 'url' => $urlBasePlugin . 'images/medios-pagos/cabal.png')
                );

                $formasPagosDisponiblesAgrupados['tarjetas_guardadas'] = $itemsCatastro;



                $formasPagosDisponiblesAgrupadosCabecera['tarjetas_guardadas'][0]['titulo'] = 'Tarjetas guardadas';
                $formasPagosDisponiblesAgrupadosCabecera['tarjetas_guardadas'][0]['imagen_principal'] = $imagenPrincipal;
                $formasPagosDisponiblesAgrupadosCabecera['tarjetas_guardadas'][0]['descripcion_principal'] = 'Se aceptan tarjetas de crédito y débito';


                $allowed_gateways['pagopar_tarjetas_guardadas'] = clone $all_gateways['pagopar'];
            }
            if (is_array($formasPagosDisponiblesAgrupados['upay'])) {
                $allowed_gateways['pagopar_upay'] = clone $all_gateways['pagopar'];
            }
            if (is_array($formasPagosDisponiblesAgrupados['tarjetas'])) {
                $allowed_gateways['pagopar_tarjetas'] = clone $all_gateways['pagopar'];
            }
            if (is_array($formasPagosDisponiblesAgrupados['bancard_qr'])) {
                $allowed_gateways['pagopar_bancard_qr'] = clone $all_gateways['pagopar'];
            }            
            if (is_array($formasPagosDisponiblesAgrupados['transferencia_bancaria'])) {
                $allowed_gateways['pagopar_transferencia_bancaria'] = clone $all_gateways['pagopar'];
            }
            if (is_array($formasPagosDisponiblesAgrupados['efectivo'])) {
                $allowed_gateways['pagopar_efectivo'] = clone $all_gateways['pagopar'];
            }
            if (is_array($formasPagosDisponiblesAgrupados['billeteras'])) {
                $allowed_gateways['pagopar_billeteras'] = clone $all_gateways['pagopar'];
            }
            if (is_array($formasPagosDisponiblesAgrupados['pix'])) {
                $allowed_gateways['pagopar_pix'] = clone $all_gateways['pagopar'];
            }

            #var_dump($allowed_gateways);die();

            if ( ($datosComerciosUpdate['datos_comercios_array']['contrato_firmado']===true) and (is_user_logged_in())) {
                $allowed_gateways['pagopar_tarjetas_guardadas']->method_title = 'Tarjetas guardadas - Pagopar';
                $allowed_gateways['pagopar_tarjetas_guardadas']->title = 'Tarjetas guardadas';
                $allowed_gateways['pagopar_tarjetas_guardadas']->id = 'pagopar_tarjetas_guardadas';
                $allowed_gateways['pagopar_tarjetas_guardadas']->datos_adicionales =    $formasPagosDisponiblesAgrupados['tarjetas_guardadas'];
                $allowed_gateways['pagopar_tarjetas_guardadas']->datos_adicionales_agrupados = $formasPagosDisponiblesAgrupadosCabecera['tarjetas_guardadas'];
            }

            # Sobreescribimos algunos datos - uPay
            if (is_array($formasPagosDisponiblesAgrupados['upay'])) {
                $allowed_gateways['pagopar_upay']->method_title = 'Tarjeta Visa y Mastercard procesado por uPay - Pagopar';
                $allowed_gateways['pagopar_upay']->title = 'Tarjetas VISA y Mastercard';
                $allowed_gateways['pagopar_upay']->id = 'pagopar_upay';
                $allowed_gateways['pagopar_upay']->method = 'pagopar';
                $allowed_gateways['pagopar_upay']->datos_adicionales = $formasPagosDisponiblesAgrupados['upay'];
                $allowed_gateways['pagopar_upay']->datos_adicionales_agrupados = $formasPagosDisponiblesAgrupadosCabecera['upay'];
            }    

            # Sobreescribimos algunos datos para agrupar -  Tarjeta
            if (is_array($formasPagosDisponiblesAgrupados['tarjetas'])) {
                $allowed_gateways['pagopar_tarjetas']->method_title = 'Tarjetas de crédito procesado por Bancard - Pagopar';
                
                if ($existeUpay==false){
                    $allowed_gateways['pagopar_tarjetas']->title = 'Tarjetas de crédito';                    
                }else{
                    $allowed_gateways['pagopar_tarjetas']->title = 'Otras tarjetas';                    
                }
                $allowed_gateways['pagopar_tarjetas']->id = 'pagopar_tarjetas';
                $allowed_gateways['pagopar_tarjetas']->method = 'pagopar';
                $allowed_gateways['pagopar_tarjetas']->datos_adicionales = $formasPagosDisponiblesAgrupados['tarjetas'];
                $allowed_gateways['pagopar_tarjetas']->datos_adicionales_agrupados = $formasPagosDisponiblesAgrupadosCabecera['tarjetas'];                
            }

            # Sobreescribimos algunos datos para agrupar -  QR
            if (is_array($formasPagosDisponiblesAgrupados['bancard_qr'])) {
                $allowed_gateways['pagopar_bancard_qr']->method_title = 'Pago QR - Pagopar';
                $allowed_gateways['pagopar_bancard_qr']->title = 'Pago QR';
                $allowed_gateways['pagopar_bancard_qr']->id = 'pagopar_bancard_qr';
                $allowed_gateways['pagopar_bancard_qr']->method = 'pagopar';
                $allowed_gateways['pagopar_bancard_qr']->datos_adicionales = $formasPagosDisponiblesAgrupados['bancard_qr'];
                $allowed_gateways['pagopar_bancard_qr']->datos_adicionales_agrupados = $formasPagosDisponiblesAgrupadosCabecera['bancard_qr'];                
            }

            


 
            # Sobreescribimos algunos datos para agrupar -  Billleteras
            if (is_array($formasPagosDisponiblesAgrupados['transferencia_bancaria'])) {
                $allowed_gateways['pagopar_transferencia_bancaria']->method_title = 'Transferencias bancarias - Pagopar';
                $allowed_gateways['pagopar_transferencia_bancaria']->title = 'Transferencias bancarias';
                $allowed_gateways['pagopar_transferencia_bancaria']->id = 'pagopar_transferencia_bancaria';
                $allowed_gateways['pagopar_transferencia_bancaria']->method = 'pagopar';
                $allowed_gateways['pagopar_transferencia_bancaria']->datos_adicionales = $formasPagosDisponiblesAgrupados['transferencia_bancaria'];
                $allowed_gateways['pagopar_transferencia_bancaria']->datos_adicionales_agrupados = $formasPagosDisponiblesAgrupadosCabecera['transferencia_bancaria'];
            }            
            
            
            # Sobreescribimos algunos datos para agrupar -  Efectivo
            if (is_array($formasPagosDisponiblesAgrupados['efectivo'])) {
                $allowed_gateways['pagopar_efectivo']->method_title = 'Efectivo - Pagopar';
                $allowed_gateways['pagopar_efectivo']->title = 'Efectivo';
                $allowed_gateways['pagopar_efectivo']->id = 'pagopar_efectivo';
                $allowed_gateways['pagopar_efectivo']->method = 'pagopar';
                $allowed_gateways['pagopar_efectivo']->datos_adicionales = $formasPagosDisponiblesAgrupados['efectivo'];
                $allowed_gateways['pagopar_efectivo']->datos_adicionales_agrupados = $formasPagosDisponiblesAgrupadosCabecera['efectivo'];
            }

            # Sobreescribimos algunos datos para agrupar -  Billleteras
            if (is_array($formasPagosDisponiblesAgrupados['billeteras'])) {
                $allowed_gateways['pagopar_billeteras']->method_title = 'Billeteras Electrónicas - Pagopar';
                $allowed_gateways['pagopar_billeteras']->title = 'Billeteras electronicas';
                $allowed_gateways['pagopar_billeteras']->id = 'pagopar_billeteras';
                $allowed_gateways['pagopar_billeteras']->method = 'pagopar';
                $allowed_gateways['pagopar_billeteras']->datos_adicionales = $formasPagosDisponiblesAgrupados['billeteras'];
                $allowed_gateways['pagopar_billeteras']->datos_adicionales_agrupados = $formasPagosDisponiblesAgrupadosCabecera['billeteras'];
            }
           
            # Sobreescribimos algunos datos para agrupar -  Billleteras
            if (is_array($formasPagosDisponiblesAgrupados['pix'])) {
                $allowed_gateways['pagopar_pix']->method_title = 'PIX - Pagopar';
                $allowed_gateways['pagopar_pix']->title = 'PIX';
                $allowed_gateways['pagopar_pix']->id = 'pagopar_pix';
                $allowed_gateways['pagopar_pix']->method = 'pagopar';
                $allowed_gateways['pagopar_pix']->datos_adicionales = $formasPagosDisponiblesAgrupados['pix'];
                $allowed_gateways['pagopar_pix']->datos_adicionales_agrupados = $formasPagosDisponiblesAgrupadosCabecera['pix'];
            }  
               
                       
            
            # Eliminamos el objeto Pagopar
            unset($allowed_gateways['pagopar']);
        } else {
            # solo retnornar un medio de pago "Pagopar"
        }
    }

    # var_dump($allowed_gateways);die();

    return $allowed_gateways;
}

add_filter('woocommerce_available_payment_gateways', 'aplicar_multimedios_pagos', 9999, 1);
?>