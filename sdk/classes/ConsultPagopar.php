<?php

/**
 * Archivo SDK de las funciones de consulta de Pagopar
 * @author "Pagopar" <desarrollo@pagopar.com>
 * @version 1.1 21/07/2017
 */

class ConsultPagopar{
    const VERSION = 'SDK 1.1';
    //URLs de configuración
    const URL_BASE = 'https://api-plugins.pagopar.com/api/';
    const URL_CATEGORIAS = 'https://api-plugins.pagopar.com/api/categorias/1.1/traer';
    const URL_CIUDADES = 'https://api-plugins.pagopar.com/api/ciudades/1.1/traer';

    //Tipos de Tokens generados
    const TOKEN_TIPO_CIUDAD = 'CIUDADES';
    const TOKEN_TIPO_CATEGORIA = 'CATEGORIAS';

    public $privateKey = null;
    public $publicKey = null;

    //Origen desde el cual se usa el SDK
    public $origin = null;

    /**
     * Constructor de la clase
     * @param string $origin Origen del request
     */
    public function __construct($origin=self::VERSION) {
        $this->origin = $origin;
    }

    /**
     * Invoca a la URL de acuerdo a los parámetros
     * @param array $args Parámetros
     * @param  string $url Url a invocar
     * @return string Respuesta en formato JSON
     */
    private function runCurl($args, $url){
        $args = json_encode($args);

        $ch = curl_init();
        $headers= array('Accept: application/json','Content-Type: application/json','X-Origin: '.$this->origin);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * Genera un Token para el pedido
     * @param string $typeOfToken Tipo de token generado
     * @return string Token generado
     */
    private function generateToken($typeOfToken){
        return sha1($this->privateKey.$typeOfToken);
    }

    /**
     * Obtiene las ciudades de los productos
     * @return array $resultado Array de objetos con los atributos de las ciudades o,
     * en caso de error, un Array con resultado "Sin datos"
     */
    public function getCities(){
        $token = $this->generateToken(self::TOKEN_TIPO_CIUDAD);

        $args = ['token'=>$token,'token_publico'=>$this->publicKey];
        $response = $this->runCurl($args, self::URL_CIUDADES);

        return json_decode($response);
    }

    /**
     * Obtiene las categorías de los productos
     * @param string $level Cadena que indica el formato en el que se retornan las categorías.
     * Pueden ser "array", "hijas", "todas"
     * @return array $resultado Array de objetos con los atributos de las categorías o,
     * en caso de error, un Array con resultado "Sin datos"
     */
    public function getProductCategories($level='hijas'){
        $token = $this->generateToken(self::TOKEN_TIPO_CATEGORIA);

        $args = ['token'=>$token,'token_publico'=>$this->publicKey,'nivel'=>$level];
        $response = $this->runCurl($args, self::URL_CATEGORIAS);
        $arrayResponse = json_decode($response);
        
        #print_r($response);die();

        return $arrayResponse->resultado;
    }

}