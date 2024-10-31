<?php


function insertar_zona_ws_pagopar($publicKey, $privateKey, $descripcion){

    $array['token_publico'] = $publicKey;
    $array['token'] = sha1($privateKey . 'ZONAS');
    $array['descripcion'] = '';

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api-plugins.pagopar.com/api/zonas/1.1/agregar-zona",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => json_encode($array),
      CURLOPT_HTTPHEADER => array(
        "Cache-Control: no-cache",
        "Content-Type: application/json"
      ),
    ));

    /*      
    CURLOPT_POSTFIELDS => "{  \r\n   \"token_publico\":\"3ceefa55009e99ea761493d8a4104740\",\r\n   \"token\":\"ce5d510b16183f6e9d9d2647659abbd5469499b7\",\r\n\t\"descripcion\": \"zona por WS\"\r\n}",
    */

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      $resultado['respuesta'] = false;
      $resultado['resultado'] = "cURL Error #:" . $err;
      #echo "cURL Error #:" . $err;
    } else {
      $resultado['respuesta'] = true;
      $response = json_decode($response, true);
      $resultado['resultado'] = $response['resultado'];
        #echo $response;
    }
    return $resultado;

}

function insertar_ciudad_ws_pagopar($publicKey, $privateKey, $zonaEnvio, $ciudad, $costo, $horas_entrega){


    $array['token_publico'] = $publicKey;
    $array['token'] = sha1($privateKey . 'ZONAS');
    $array['zona_envio'] = $zonaEnvio;
    $array['ciudad'] = $ciudad;
    $array['costo'] = $costo;
    $array['horas_entrega'] = $horas_entrega;

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api-plugins.pagopar.com/api/zonas/1.1/agregar-ciudad",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => json_encode($array),
      CURLOPT_HTTPHEADER => array(
        "Cache-Control: no-cache",
        "Content-Type: application/json"
      ),
    ));

   
    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      $resultado['respuesta'] = false;
      $resultado['resultado'] = "cURL Error #:" . $err;
    } else {
      $resultado['respuesta'] = true;
      $response = json_decode($response, true);
      $resultado['resultado'] = $response['resultado'];
    }
    return $resultado;
}


function eliminar_ciudad_ws_pagopar($publicKey, $privateKey, $zonaEnvio, $ciudad){


    $array['token_publico'] = $publicKey;
    $array['token'] = sha1($privateKey . 'ZONAS');
    $array['zona_envio'] = $zonaEnvio;
    $array['ciudad'] = $ciudad;

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api-plugins.pagopar.com/api/zonas/1.1/eliminar-ciudad",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => json_encode($array),
      CURLOPT_HTTPHEADER => array(
        "Cache-Control: no-cache",
        "Content-Type: application/json"
      ),
    ));

   
    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      $resultado['respuesta'] = false;
      $resultado['resultado'] = "cURL Error #:" . $err;
    } else {
      $resultado['respuesta'] = true;
      $response = json_decode($response, true);
      $resultado['resultado'] = $response['resultado'];
    }
    return $resultado;
}



/* Medios de Pago */


function traer_medios_pago_disponibles($publicKey, $privateKey){


    $array['token_publico'] = $publicKey;
    $array['token'] = sha1($privateKey . 'FORMA-PAGO');


    $curl = curl_init();

    
    /*temp

     CURLOPT_SSL_VERIFYPEER => false,        
     CURLOPT_SSL_VERIFYHOST => 0,
            
     */
    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api-plugins.pagopar.com/api/forma-pago/1.1/traer",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => json_encode($array),
        
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

    if ($err) {
      $resultado['respuesta'] = false;
      $resultado['resultado'] = "cURL Error #:" . $err;
    } else {
      $resultado['respuesta'] = true;
      $response = json_decode($response, true);
      $resultado['resultado'] = $response['resultado'];
    }
    return $resultado;
}



function traer_comercios_hijos_asociados($publicKey, $privateKey){

    $array['token_publico'] = $publicKey;
    $array['token'] = sha1($privateKey . 'COMERCIO-HEREDADO');


    $curl = curl_init();

    

    curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api-plugins.pagopar.com/api/comercio-heredado/1.1/traer-comercios-hijos/",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode($array),
        
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

    if ($err) {
      $resultado['respuesta'] = false;
      $resultado['resultado'] = "cURL Error #:" . $err;
    } else {
      $resultado['respuesta'] = true;
      $response = json_decode($response, true);
      $resultado['resultado'] = $response['resultado'];
    }
    return $resultado;
}


?>