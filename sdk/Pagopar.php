<?php

/**
 * Archivo SDK de Pagopar
 * @author "Pagopar" <desarrollo@pagopar.com>
 * @version 1.1 21/07/2017
 */
require_once 'lib/DBPagopar.php';

require_once 'classes/OrderPagopar.php';
require_once 'classes/ConsultPagopar.php';

class Pagopar {

    const VERSION = 'SDK 1.1';
    //URLs de configuración
    const URL_BASE = 'https://api-plugins.pagopar.com/api/';
    const URL_COMERCIOS = 'https://api-plugins.pagopar.com/api/comercios/1.1/iniciar-transaccion';
    const URL_PEDIDOS = 'https://api-plugins.pagopar.com/api/pedidos/1.1/traer';
    const URL_FLETE = 'https://api-plugins.pagopar.com/api/calcular-flete/1.1/traer';
    const URL_CATEGORIAS = 'https://api-plugins.pagopar.com/api/categorias/1.1/traer';
    const URL_CIUDADES = 'https://api-plugins.pagopar.com/api/ciudades/1.1/traer';
    const URL_REDIRECT = 'https://www.pagopar.com/pagos/%s';
    //Tipos de Tokens generados
    const TOKEN_TIPO_CONSULTA = 'CONSULTA';
    const TOKEN_TIPO_CIUDAD = 'CIUDADES';
    const TOKEN_TIPO_CATEGORIA = 'CATEGORIAS';
    const TOKEN_TIPO_FLETE = 'CALCULAR-FLETE';
    const TOKEN_TIPO_PAGO_RECURRENTE = 'PAGO-RECURRENTE';

    //Url de Catastro de Tarjetas
    const URL_ADD_CLIENT = 'https://api-plugins.pagopar.com/api/pago-recurrente/1.1/agregar-cliente/';
    const URL_ADD_TARJETA = 'https://api-plugins.pagopar.com/api/pago-recurrente/1.1/agregar-tarjeta/';
    const URL_CONFIRM_TARJETA = 'https://api-plugins.pagopar.com/api/pago-recurrente/1.1/confirmar-tarjeta/';
    const URL_LIST_TARJETA = 'https://api-plugins.pagopar.com/api/pago-recurrente/2.0/listar-tarjeta/';
    const URL_DELETE_TARJETA = 'https://api-plugins.pagopar.com/api/pago-recurrente/2.0/eliminar-tarjeta/';
    const URL_PAY_WITH_TARJETA = 'https://api-plugins.pagopar.com/api/pago-recurrente/2.0/pagar/';

    //Base de datos
    protected $db;
    //Datos del pedido del comercio
    private $idOrder;
    private $hashOrder;
    public $order;
    private $preOrder;
    private $itemsIdMethods;
    public $privateKey = null;
    public $publicKey = null;
    //Origen desde el cual se usa el SDK
    public $origin = null;

    /**
     * Constructor de la clase
     * @param int $id Id del pedido
     * @param $db
     * @param string $origin Origen del request
     * @internal param Database $PDO $db Base de Datos (Basada en PDO)
     */
    public function __construct($id = null, $db, $origin = self::VERSION) {
        $this->db = $db;
        $this->idOrder = $id;
        $this->order = new OrderPagopar($id);
        $this->origin = $origin;
    }

    /**
     * Invoca a la URL de acuerdo a los parámetros
     * @param array $args Parámetros
     * @param  string $url Url a invocar
     * @param string $origin Origen del request
     * @return string Respuesta en formato JSON
     */
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
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * Guarda en un array ($itemsIdMethods) los id_producto de los ítems y sus índices en el array
     * compras_items ([id_producto => índice]) para que tengamos un acceso más rápido a los ítems.
     * Además, para los ítems con más de un método de envío, guardamos los tipos de envío disponibles
     * en un array ($multiple_methods) con sus valores para retornar después al usuario.
     * @param array $arrayResponse Array que contiene las distintas opciones de envío de los ítems.
     * @return string Respuesta en formato JSON que contiene las distintas opciones de envío de los ítems.
     * Si se retorna false, se asume que no existen opciones de envío o que sólo existe una opción
     * de envío para cada ítem.
     */
    private function saveMethodsOfShipping($arrayResponse) {
        $multiple_methods = [];
        $this->itemsIdMethods = [];

        //Si alguno de los ítems devueltos tiene los dos métodos de pago,
        foreach ($arrayResponse->compras_items as $index => $item) {
            //En un array guardamos los ids de los productos como índices y los índices como valor
            //para que sean más fáciles de encontrar después.

            /*if ((is_numeric($product['variation_id'])) and ( $product['variation_id'] > 0)) {
                $idProductoReal = $product['variation_id'];
            } else {
                $idProductoReal = $product['product_id'];
            }*/

            $this->itemsIdMethods[$item->id_producto] = $index;
            if (isset($item->opciones_envio)) {
            # Se comentó la siguiente linea y se deja la anterior debido a que ocasionaba que si solo tenia un medio de envio, se seleccione ese y confirme el pedido automaticamente. 
            #if (isset($item->opciones_envio) && isset($item->opciones_envio->metodo_aex) && isset($item->opciones_envio->metodo_propio)) {
                $multiple_methods[$item->id_producto] = [
                    "aex" => $item->opciones_envio->metodo_aex,
                    "propio" => $item->opciones_envio->metodo_propio,
                    "retiro" => $item->opciones_envio->metodo_retiro,
                ];
            }
        }

        //Guardamos la respuesta en preOrder
        $this->preOrder = $arrayResponse;

        return (!empty($multiple_methods)) ? json_encode($multiple_methods) : false;
    }

    /**
     * Inicia la conexión con Pagopar y obtiene los métodos de envío para cada ítem
     * @return string|boolean Respuesta en formato JSON que contiene las distintas opciones de envío de los ítems.
     * Si se retorna false, se asume que no existen opciones de envío o que sólo existe una opción
     * de envío para cada ítem.
     */
    public function getMethodsOfShipping() {
        $orderPagopar = $this->order->makeOrder();

        $token = $this->generateToken(self::TOKEN_TIPO_FLETE);

        /*$args = ['token' => $token, 'token_publico' => $this->order->publicKey, 'dato' => json_encode($orderPagopar)];
        $response = $this->runCurl($args, self::URL_FLETE);*/
        $var = WC()->session->get('pagopar_order_flete');
        $arrayResponse = json_decode($var);


        if (isset($arrayResponse->respuesta) && !$arrayResponse->respuesta) {
            throw new Exception($arrayResponse->resultado);
        } elseif (isset($arrayResponse->resultado) && $arrayResponse->resultado === "Sin datos") {
            throw new Exception($arrayResponse->resultado);
        }

        return $this->saveMethodsOfShipping($arrayResponse);
    }

    /**
     * Valida el JSON de los métodos de envío seleccionados.
     * @param array $items Array con los métodos de envío seleccionados. Tiene esta estructura:
     * ["id_producto_1" => "metodo_1", "id_producto_2" => "metodo_2", ... => ...]
     * @return bool True si el JSON es válido (los id de los productos existen y los métodos de pago existen),
     * False si no es un JSON válido.
     */
    private function validateItemsMethodsJSON($items) {
        foreach ($items as $id => $method) {
            if (array_key_exists($id, $this->itemsIdMethods)) {
                if (property_exists($this->preOrder->compras_items[$this->itemsIdMethods[$id]]->opciones_envio, 'metodo_' . $method)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * Completa los ítems de la orden con el campo envio_seleccionado
     * @param string $json_items Cadena JSON que contiene las opciones de envío de los ítems.
     * @return array Array de respuesta que contiene la Orden con el flete calculado de los ítems o
     * un array de error en caso de fallo.
     * @throws Exception
     */
    private function fillShippingOptions($json_items, $no_replace_items = false) {
        $items_methods = null;
        if ($json_items) {
            $items_methods = json_decode($json_items);
            #var_dump($items_methods);die();
            if (!$this->validateItemsMethodsJSON($items_methods)) {
                throw new Exception("Hay un error con el JSON de los métodos seleccionados de los items");
            }
        }

        foreach ($this->preOrder->compras_items as $index => $item) {
            //Obtenemos el primer y único elemento en el caso de que sólo haya un método seleccionado
            if (isset($item->opciones_envio)) {
                $item->envio_seleccionado = false;
                foreach ($item->opciones_envio as $metodo => $value) {
                    if ($value) {
                        $item->envio_seleccionado = ($metodo == "metodo_aex") ? "aex" : (($metodo == "metodo_propio") ? "propio" : "retiro");
                    }
                }
            }
        }

        //Completamos los campos necesarios para envio_seleccionado
        if ($json_items && !$no_replace_items) {
            foreach ($items_methods as $id => $method) {
                $renamed_method = ($method == "aex") ? "aex" : (($method == "propio") ? "propio" : "retiro");
                $this->preOrder->compras_items[$this->itemsIdMethods[$id]]->envio_seleccionado = $renamed_method;
            }
        }

        //Calculamos el flete
        return $this->getShippingCost();
    }

    /**
     * Calcula el flete de los ítems si es necesario
     * @return array Array de respuesta que contiene la Orden con el flete calculado de los ítems o
     * un array de error en caso de fallo.
     */
    private function getShippingCost() {
        $token = $this->generateToken(self::TOKEN_TIPO_FLETE);

        $args = ['token' => $token, 'token_publico' => $this->order->publicKey, 'dato' => json_encode($this->preOrder)];
        $response = $this->runCurl($args, self::URL_FLETE);

        return json_decode($response);
    }

    /**
     * Reenvía el pedido a Pagopar (con el flete calculado) y genera una transacción. Si tiene éxito al
     * generar el pedido, redirecciona a la página de pago de Pagopar.
     * @param string $json_items Cadena JSON que contiene las opciones de envío de los ítems.
     * Si no recibe este parámetro, se asume que no existen opciones de envío o que sólo existe una opción
     * de envío para cada ítem.
     * @param boolean $redirect Bandera que indica si el SDK debe redireccionar a Pagopar o sólo retornar
     * la URL para redireccionar
     * @return string $url si no se redirecciona a Pagopar
     * @throws Exception
     */
    public function newPagoparTransaction($json_items = null, $redirect = true, $no_replace_items = false, $formaPagoRedirect = null) {

        $response = $this->fillShippingOptions($json_items, $no_replace_items);

        //Verificar si hay error
        if (isset($response->respuesta) && !$response->respuesta) {
            throw new Exception($response->resultado);
        }
        $user = wp_get_current_user();
        //Asignamos de nuevo a la orden la respuesta
        $this->preOrder = $response;

        //Generamos de nuevo el token
        $this->preOrder->token = $this->order->generateOrderHash(
                $this->preOrder->id_pedido_comercio, $this->preOrder->monto_total, $this->order->privateKey
        );

        $response = $this->runCurl($this->preOrder, self::URL_COMERCIOS);
        $arrayResponse = json_decode($response);



        //Verificar si hay error
        if (!$arrayResponse->respuesta) {
            throw new Exception($arrayResponse->resultado);
        }

        $this->hashOrder = $arrayResponse->resultado[0]->data;

        $this->db->insertTransaction(
                $this->preOrder->id_pedido_comercio, $this->preOrder->tipo_pedido, $this->preOrder->monto_total, $this->hashOrder, $this->preOrder->fecha_maxima_pago, $this->preOrder->descripcion_resumen
        );

        $pp_forma_array = explode("-", $formaPagoRedirect);
        if ($pp_forma_array[0] === "catastro") {
            $pp_result = $this->pagoConcurrente($user->ID, $pp_forma_array[1], $this->hashOrder);
            return site_url()."/gracias-por-su-compra/?hash=".$this->hashOrder;
        } else {
            if ($redirect) {
                $this->redirectToPagopar($this->hashOrder, $formaPagoRedirect);
            } else {
                if (is_numeric($formaPagoRedirect)) {
                    return sprintf(self::URL_REDIRECT, $this->hashOrder) . '?forma_pago=' . $formaPagoRedirect;
                } else {
                    return sprintf(self::URL_REDIRECT, $this->hashOrder);
                }
            }
        }



        return null;
    }

    function pagoConcurrente($id, $card, $hash) {
        $token = $this->generateToken(self::TOKEN_TIPO_PAGO_RECURRENTE);

        $args = [
            'identificador' => $id,
            'token' => $token,
            'token_publico' => $this->order->publicKey,
            'tarjeta' => $card,
            'hash_pedido' => $hash
        ];
        $response = $this->runCurl($args, self::URL_PAY_WITH_TARJETA);

        return $response;
    }

    /**
     * Redirecciona a la página de Pagopar
     * @param string $hash Hash del pedido
     */
    public function redirectToPagopar($hash, $formaPagoRedirect = null) {
        $url = sprintf(self::URL_REDIRECT, $hash);

        //Redireccionamos a Pagopar
        if (is_numeric($formaPagoRedirect)) {
            header('Location: ' . $url . '?forma_pago=' . $formaPagoRedirect);
        } else {
            header('Location: ' . $url);
        }
        exit();
    }

    public function setKeys($public_key, $private_key) {
        $this->order->publicKey = $public_key;
        $this->order->privateKey = $private_key;        
    }
    /**
     * Inicia la transacción con Pagopar y si tiene éxito al generar el pedido con valores de prueba,
     * redirecciona a la página de pago de Pagopar.
     */
    public function newTestPagoparTransaction($public_key, $private_key, $seller_public_key) {
        //Creamos el comprador
        $buyer = new BuyerPagopar();
        $buyer->name = 'Juan Perez';
        $buyer->cityId = 1;
        $buyer->tel = '0972200046';
        $buyer->typeDoc = 'CI';
        $buyer->doc = '352221';
        $buyer->addr = 'Mexico 840';
        $buyer->addRef = 'alado de credicentro';
        $buyer->addrCoo = '-25.2844638,-57.6480038';
        $buyer->ruc = null;
        $buyer->socialReason = null;

        //Agregamos el comprador
        $this->order->addPagoparBuyer($buyer);

        //Creamos los productos
        $item1 = new ItemPagopar();
        $item1->name = "Válido 1 persona";
        $item1->qty = 1;
        $item1->price = 1000;
        $item1->cityId = 1;
        $item1->desc = "producto";
        $item1->url_img = "http://www.clipartkid.com/images/318/tickets-for-the-film-festival-are-for-the-two-day-event-admission-is-lPOEYl-clipart.png";
        $item1->weight = '0.1';
        $item1->category = 3;
        $item1->productId = 100;
        $item1->sellerPhone = '0985885487';
        $item1->sellerAddress = 'dr paiva ca cssssom gaa';
        $item1->sellerAddressRef = '';
        $item1->sellerAddressCoo = '-28.75438,-57.1580038';
        $item1->sellerPublicKey = $seller_public_key;

        $item2 = new ItemPagopar();
        $item2->name = "Heladera";
        $item2->qty = 1;
        $item2->price = 785000;
        $item2->cityId = 1;
        $item2->desc = "producto";
        $item2->url_img = "https://cdn1.hendyla.com/archivos/imagenes/2017/04/09/publicacion-564c19b86b235526160f43483c76a69ee1a85c96c976c33e3e21ce6a5f9009b9-234_A.jpg";
        $item2->weight = '5.0';
        $item2->category = 8; //Cambiar a 8
        $item2->productId = 2;
        $item2->sellerPhone = '0985885487';
        $item2->sellerAddress = 'dr paiva ca cssssom gaa';
        $item2->sellerAddressRef = '';
        $item2->sellerAddressCoo = '-28.75438,-57.1580038';
        $item2->sellerPublicKey = $seller_public_key;

        //Agregamos los productos al pedido
        $this->order->addPagoparItem($item1);
        $this->order->addPagoparItem($item2);

        $this->order->publicKey = $public_key;
        $this->order->privateKey = $private_key;
        $this->order->typeOrder = 'VENTA-COMERCIO';
        $this->order->desc = 'Entrada Retiro';
        $this->order->periodOfDaysForPayment = 1;
        $this->order->periodOfHoursForPayment = 0;

        /* Calculamos el flete y... si es algún ítem tiene opcion_envio metodo_aex Y propio, entonces retornamos
          un json en el que el usuario debe seleccionar el método de pago para el ítem específico */
        $json_pedido_con_flete = $this->getMethodsOfShipping();

        if (!$json_pedido_con_flete) {
            $this->newPagoparTransaction();
        } else {
            /* Seleccionamos los métodos de envío y después generamos la nueva transacción
              Método para seleccionar el envío, debe ser una pantalla intermedia
              y hay que llamar a la función newPagoparTransaction con un array como parámetro.
              El json debe tener esta forma:
              {
              id_producto_1 : metodo_seleccionado_1,
              id_producto_2 : metodo_seleccionado_2,
              ...           : ...
              }
             */
            //$json_items_metodo_seleccionado = '{"100":"aex"}';
            //$pedidoPagopar->newPagoparTransaction($json_items_metodo_seleccionado);
        }
    }

    /**
     * Obtiene un JSON con el estado del pedido
     * @param int $id Id del pedido
     * @return JSON con el estado del Pedido
     * @throws Exception
     */
    public function getPagoparOrderStatus($id) {
        $this->idOrder = $id;
        $orderData = $this->db->selectTransaction("id=$id");
        if ($orderData) {
            $this->hashOrder = $orderData['hash'];
        } else {
            throw new Exception("Hay un error con el hash");
        }
        $token = $this->generateToken(self::TOKEN_TIPO_CONSULTA);

        $args = ['hash_pedido' => $this->hashOrder, 'token' => $token, 'token_publico' => $this->publicKey];
        $arrayResponse = $this->runCurl($args, self::URL_PEDIDOS);

        return $arrayResponse;
    }

    /**
     * Genera un Token para el pedido
     * @param string $typeOfToken Tipo de token generado
     * @return string Token generado
     */
    private function generateToken($typeOfToken) {
        $key = ($this->privateKey) ? $this->privateKey : $this->order->privateKey;
        return sha1($key . $typeOfToken);
    }

    #registrar usuario

    public function registrarUsuario(array $json) {
        $url = self::URL_BASE . 'usuario/1.1/registro';
        $args = $json;
        $arrayResponse = $this->runCurl($args, $url);
        return $arrayResponse;
    }

}