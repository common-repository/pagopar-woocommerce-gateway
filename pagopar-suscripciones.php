<?php

function pagopar_task_function() {
    return true;
    
global $wpdb;
    $tabla_pagos = $wpdb->prefix.'pagopar_pagos_automaticos';
    $tabla_pagos_detalle = $wpdb->prefix.'pagopar_pagos_automaticos_detalle';
    //traemos todas las ordenes creadas 
    $ordenes = $wpdb->get_results("SELECT order_id, user_id FROM ".$tabla_pagos." WHERE activo = 1 ORDER BY fecha_creacion ASC");
    
    foreach ($ordenes as $item) {
      
        $orden_detalle = $wpdb->get_results("SELECT pago_detalle_id,product_id,pagado,fecha_ultimo_pago,pagos_a_realizar,pagos_realizados,fecha_proximo_pago FROM ".$tabla_pagos_detalle." WHERE pagado = 0 AND pagos_a_realizar > pagos_realizados AND pago_id =".$item->order_id);

        
        $product_ids_a_pagar = [];

        foreach ($orden_detalle as $item_detalle) {
          
          $fecha_proximo_pago = $item_detalle->fecha_proximo_pago;
          $periodicidad =  get_post_meta($item_detalle->product_id, 'product_subscription_date', true);
          
          if (date('Y-m-d') >= date('Y-m-d', strtotime($fecha_proximo_pago))) {
            array_push($product_ids_a_pagar, $item_detalle->product_id);
          }
        }

        if (isset($product_ids_a_pagar)) {

          $old_order = wc_get_order($item->order_id);
          $old_order_detail = new WC_Order($item->order_id);
          
          $shipping_address = $old_order_detail->data['shipping'];
          $billing_address = $old_order_detail->data['billing'];
  
          $shipping_item_data = null; 
          foreach($old_order->get_items('shipping') as $item_id => $shipping_item_obj){
            // Get the data in an unprotected array
            $shipping_item_data = $shipping_item_obj->get_data();
          }
          

          global $woocommerce;

                    // Now we create the order
          $order = wc_create_order();
          


          foreach ($old_order->get_items() as $item_id => $orderitem ) {
            $product_id = $orderitem->get_product_id();
            if (in_array($product_id, $product_ids_a_pagar)) {
              $quantity = $orderitem->get_quantity();
              $order->add_product(get_product($product_id), $quantity);
            }
          }
          
          if($shipping_item_data != null && $shipping_item_data['total'] > 0) {
            $new_item = new WC_Order_Item_Shipping();
            $new_item->set_name($shipping_item_data['name']);
            $new_item->set_method_title($shipping_item_data['method_title']);
            $new_item->set_method_id($shipping_item_data['method_id']); // set an existing Shipping method rate ID
            $new_item->set_total($shipping_item_data['total']); // (optional)
            
            
            $order->add_item($new_item);
          }

          // Set addresses
          $order->set_address($shipping_address, 'shipping');
          $order->set_address($billing_address, 'billing');
          
          
          // Set payment gateway
          $payment_gateways = WC()->payment_gateways->payment_gateways();

          $order->set_payment_method($payment_gateways['pagopar_tarjetas']);
          
          // Calculate totals
          $order->calculate_totals();
          
          $estadoPagopar = $payments['pagopar']->settings['estado_creacion_pedido_pagopar'];
          if (substr($estadoPagopar, 0, 3) === 'wc-')
          {
              $estadoPagopar = substr($estadoPagopar, 3);
          }
          if ($estadoPagopar == '')
          {
              $estadoPagopar = 'processing';
          }

          $order->update_status($estadoPagopar, 'Procesando pedido (No pagado).');

          
          $order_id = $order->get_id();
          
          $user = get_user_by('id', $item->user_id);
          
          $apiUrl = "https://api-plugins.pagopar.com/api/pago-recurrente/2.0/listar-tarjeta/";
          $response = pagoparCurl(null, null, $apiUrl, false, 'PAGO-RECURRENTE', null, false, $user);

          $responseDecode = json_decode($response);
  
          if (boolval($responseDecode->respuesta)) { 
            $pagado = false;
            $list_tarjetas = $responseDecode->resultado;
            
            
            foreach ($list_tarjetas as $key => $tarjeta) {
              $publicKey = $payments['pagopar']->settings['public_key'];
              $privateKey = $payments['pagopar']->settings['private_key'];
              $token = sha1($privateKey . 'PAGO-RECURRENTE');

              $cityCode = str_replace("PY", "", $shipping_address['state']);
              $hash_resultado = get_suscription_hash($order_id, $shipping_item_data['method_title'], $cityCode, $order_id, $item->user_id);

              if ($response == null || $response->respuesta === false)
              {
                  return null;
              }
              $hash = $response->resultado[0]->data;

              $args = [
                  'identificador' => $item->user_id,
                  'token' => $token,
                  'token_publico' => $publicKey,
                  'tarjeta' => $tarjeta->alias_token,
                  'hash_pedido' => $hash
              ];

              $response = runCurl($args, "https://api-plugins.pagopar.com/api/pago-recurrente/2.0/pagar/");
              
              $resultado = json_decode($response);

              if($resultado->respuesta == true) {
                $pagado = true;
                foreach ($orden_detalle as $item_detalle) {
                  
                  $cantidad_pagos = get_post_meta($item_detalle->product_id, 'product_suscription_quantity', true);
                  $periodicidad = get_post_meta($item_detalle->product_id, 'product_subscription_date', true);
  
                  $fecha = date("Y/m/d");
  
                  if ($periodicidad == 7) {
                      $fecha = date('Y/m/d', strtotime("+7 days", strtotime($fecha)));
                  } else if ($periodicidad == 30) {
                      $fecha = date('Y/m/d', strtotime("+1 months", strtotime($fecha)));
                  }
                  $wpdb->get_results("UPDATE `".$tabla_pagos_detalle."` 
                                                     SET `fecha_ultimo_pago` = '".date('Y-m-d')."',
                                                         `pagos_realizados` = `pagos_realizados`+1,
                                                         `fecha_proximo_pago` = '".$fecha."'
                                                     WHERE `pago_detalle_id` = ".$item_detalle->pago_detalle_id.";");
                }
                break;
              } 

            }
            if($pagado == false) {
              $order->update_status( 'cancelled' );
            }

            
    
            
          }


        }
        
        
        
    }
}

function get_suscription_hash($order_id, $metodo_seleccionado_post, $cityId, $newOrderId, $userId) {
        require_once 'sdk/Pagopar.php';
        global $woocommerce;
        $metodo_seleccionado = null;
        $monto_delivery = 0;


   
        # se define cual metodo de envio se utilizara, hay que rever esto
        if(strpos($metodo_seleccionado_post, "Retiro") !== false) {
            $metodo_seleccionado = "retiro";
        } else {
            if (strpos($metodo_seleccionado_post, "AEX") !== false) {
                $metodo_seleccionado = "aex";
            } elseif (strpos($metodo_seleccionado_post, "MOBI") !== false) {
                $metodo_seleccionado = "mobi";
            } else{
                $metodo_seleccionado = "propio";
            }
        }
        
        
        #var_dump($metodo_seleccionado_post);
        #var_dump($chosen_shipping_methods);
        #die();
        
        

        $pagopar_calcular_flete = calculate_flete_suscription($cityId, $newOrderId, $userId);
        if($pagopar_calcular_flete->respuesta == false) {
          return null;
        }
        $conAex = $pagopar_calcular_flete !== null;
        $arrayResponse = $pagopar_calcular_flete === null ? null : $pagopar_calcular_flete;

        
        $newOrderPagopar = array();

        $newOrderPagopar['id_pedido_comercio'] = $order_id;
        $order = wc_get_order($order_id);
        //We obtain the items
        $order_items = $order->get_items();
        
        //Instanciates wordpress database
        global $wpdb;
        $db = new DBPagopar(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, $wpdb->prefix."transactions_pagopar", $wpdb->prefix."pagopar_pagos_automaticos", $wpdb->prefix."pagopar_pagos_automaticos_detalle");

        //Create New Pagopar order
        $this->pedidoPagopar = new Pagopar($order_id, $db, $this->origin);

        $payments = WC()
            ->payment_gateways
            ->payment_gateways();

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
        //$buyer = new BuyerPagopar();
        //$buyer->name = $order->billing_first_name . ' ' . $order->billing_last_name;
        //$buyer->email = $order->billing_email;
        //$buyer->cityId = $_POST['billing_ciudad'];
        //$buyer->tel = $order->billing_phone;
        //$buyer->typeDoc = 'CI';
        $doc = null;
        $ruc = null;
        $nombreCampoRuc = null;
        $nombreCampoRazonSocial = null;
        $socialReason = null;
        # Si se definio un campo alternativo para documento, usamos ese
        if (($documentoAlternativo != '') and ($documentoAlternativo != 'billing_documento'))
        {
            //$buyer->doc = $_POST[$documentoAlternativo];
            $doc = $_POST[$documentoAlternativo];
        }
        else
        {
            //$buyer->doc = $_POST['billing_documento'];
            $doc = $_POST['billing_documento'];
        }
        $addr = $order->shipping_address_1;
        $addRef = $_POST['billing_referencia'];
        $addrCoo = '';

        //$buyer->addr = $order->billing_address_1;
        //$buyer->addRef = $_POST['billing_referencia'];
        //$buyer->addrCoo = '';

        # Actualizamos direccion de envio de Woocommerce si corresponde
        /*if (trim($order->billing_address_1) != '')
        {
            update_post_meta($order->id, '_shipping_first_name', $order->billing_first_name);
            update_post_meta($order->id, '_shipping_last_name', $order->billing_last_name);
            update_post_meta($order->id, '_shipping_address_1', $order->billing_address_1);
        }*/

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
        //if (trim($buyer->socialReason) === '')
        //{
        //    $buyer->socialReason = $razonSocialDefecto;
        //}
//
        //if (trim($buyer->ruc) === '')
        //{
        //    $buyer->ruc = $rucDefecto;
        //}
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

        if($woocommerce_ship_to_destination === 'billing_only')
        {
            $state_code = $_POST['billing_state'];
        } else {
            $state_code = $_POST['shipping_state'];
        }

        
        $ciudad_id = str_replace("PY", "", $state_code);
        $comprador = array(
            "nombre" => $order->billing_first_name . ' ' . $order->billing_last_name,
            "ciudad"=> $ciudad_id,
            "email"=>$order->billing_email,
            "telefono"=>$order->billing_phone,
            "tipo_documento"=>"CI",
            "documento"=>$doc,
            "direccion"=>$addr,
            "direccion_referencia"=>$addRef,
            "coordenadas"=>$addrCoo,
            "ruc"=>$ruc,
            "razon_social"=>$socialReason
        );
        $newOrderPagopar['comprador'] = $comprador;

        //$this
          //  ->pedidoPagopar
          //  ->order
          //  ->addPagoparBuyer($buyer);

        $items = $arrayResponse->compras_items;
        
        $order_shippings = $order->get_shipping_methods();
        foreach ($order_shippings as $shipping)
        {
            $shippingName = $shipping['name'];
            $shippingAmount = $shipping['total'];
            if ($shippingAmount > 0)
            {
                //$item = new ItemPagopar();
                //$item->name = $shippingName;
                //$item->qty = 1;
                //$item->price = $shippingAmount;
                //$item->cityId = ($city) ? $city : $this->seller_ciudad;
                //$item->desc = $shippingName;
                //$item->url_img = '';
//
                //$item->category = 909;
                //$item->productId = $p_id;
                //$item->sellerPhone = ($phone) ? $phone : $this->seller_phone;
                //$item->sellerAddress = ($addr) ? $addr : $this->seller_addr;
                //$item->sellerAddressRef = ($addr_ref) ? $addr_ref : $this->seller_addr_ref;
                //$item->sellerAddressCoo = ($coo) ? $coo : $this->seller_coo;
                //$item->sellerPublicKey = $this->public_key;
                //$item->weight = '';
                //$item->large = '';
                //$item->width = '';
                //$item->height = '';
                //$item->retiroObs = '';
//
                //$this
                //    ->pedidoPagopar
                //    ->order
                //    ->addPagoparItem($item);
                $monto_delivery = $shippingAmount;
            }
        }
        
        
        $items_nuevo_pedido = [];
        $ids_recurrentes = [];

        //Crear la lista de items dependiendo de si hay o no  productos fisicos en el carrito
        if($conAex) {
                $metodo_propio= [];
        
                $pagopar_envio_aex_pickup_horario_fin = intval(get_post_meta($idProductoPadre, 'pagopar_envio_aex_pickup_horario_fin', true)); 
                if ($pagopar_envio_aex_pickup_horario_fin===0){
                    $pagopar_envio_aex_pickup_horario_fin = 48;
                }
                # Armamos envio propio
                foreach($rates as $rate_key => $rate){
                    # Los flat_rate  de Woocommerce son los que consideramos como envio propio en Pagopar
                    if ($rates[$rate_key]->method_id === 'flat_rate'){
                        $propio = array(
                          "tiempo_entrega" => $pagopar_envio_aex_pickup_horario_fin,
                          "destino" => $ciudad_id,
                          "precio" => floatval($rates[$rate_key]->cost)
                        );
                        array_push($metodo_propio, $propio);
                    }
                    
                }
                
                # Armamos recogida del local
                $retiroSucursal = [];
                foreach($rates as $rate_key => $rate){
                    # Los local_pickup de Woocommerce son los que consideramos como retiro de sucursal en Pagopar
                    if ($rates[$rate_key]->method_id === 'local_pickup'){
                        $recogidaLocal = array(
                            "observacion" => $rates[$rate_key]->label
                        );
                        $retiroSucursal2['observacion'] = $rates[$rate_key]->label;
                        array_push($retiroSucursal, $recogidaLocal);
                    }
                    
                }

                $metodos_propio_array = $metodo_propio;
                $metodos_retiro_local_array = $retiroSucursal;
                /*temp$metodo_propio= [];
                foreach($metodos_propio_array as $propio){
                  $propio = array(
                    "tiempo_entrega" => $propio->tiempo_entrega,
                    "destino" => $propio->destino,
                    "precio" => floatval($propio->precio)
                  );
                  array_push($metodo_propio, $propio);
                  
                }*/
                
                
                
            foreach ($items as $item) {
                $metodos = array();
                $metodos = $item->opciones_envio;
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


                $itemPagopar = array(
                        "nombre"=>$item->nombre,
                        "cantidad"=>$item->cantidad,
                        "precio_total"=>$item->precio_total,
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
                    
                    # verificar si utilizar cart o order
                    $totalDescuento = intval(WC()->cart->get_discount_total());
                    $totalDescuentoRestante = intval($totalDescuento);

                    # Parche mientras se habilite descuento en API 2.0 en Pagopar
                    $descripcionDescuento = '';
                    $subtotal = $product['total'];
                    # SI hay un descuento pendiente por restar para que cuadren los números
                    if ($totalDescuentoRestante > 0){
                        $subtotal = $subtotal - $totalDescuentoRestante;
                        # Para ver si se necesita volver a descontar en el siguiente item
                        $totalDescuentoRestante = $subtotal - $totalDescuentoRestante;
                        $descripcionDescuento = ' menos descuento';

                    }
   

                    $itemPagopar = array(
                        "nombre"=>$product['name'],
                        "cantidad"=>$product['quantity'],
                        "precio_total"=>$subtotal,
                        "ciudad"=>($city) ? $city : $this->seller_ciudad,
                        "descripcion"=>$product['name'],
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
        }
        
        //We add items
        // foreach ($order_items as $product)
        // {

        //     if ((is_numeric($product['variation_id'])) and ($product['variation_id'] > 0))
        //     {
        //         $idProductoReal = $product['variation_id'];
        //     }
        //     else
        //     {
        //         $idProductoReal = $product['product_id'];
        //     }

        //     # En este caso usamos el Id del producto padre (sin variación) ya que cuando se guardan los datos
        //     # de pagopar se guarda por este id, no por el id de variacion del producto
        //     $p_id = $product['product_id'];
        //     #$p_id = $product['product_id'];


        //     $phone = get_post_meta($p_id, 'product_seller_phone', true);
        //     $addr = get_post_meta($p_id, 'product_seller_addr', true);
        //     $addr_ref = get_post_meta($p_id, 'product_seller_addr_ref', true);
        //     $coo = get_post_meta($p_id, 'product_seller_coo', true);
        //     $city = get_post_meta($p_id, 'product_seller_ciudad', true);
        //     $weight = get_post_meta($p_id, 'product_weight', true);
        //     $largo = get_post_meta($p_id, 'pagopar_largo', true);
        //     $ancho = get_post_meta($p_id, 'pagopar_ancho', true);
        //     $alto = get_post_meta($p_id, 'pagopar_alto', true);
        //     $retiro_obs = get_post_meta($p_id, 'product_sucursal_obs', true);
        //     $json_propio = get_post_meta($p_id, 'product_envios_propios', true);
        //     $comercio_hijo_vendedor_producto = get_post_meta($p_id, 'comercio_hijo_vendedor_producto', true);
        //     $splitBillingHabilitado = $this->splitBillingHabilitado($habilitar_split_billing, $comercio_hijo_vendedor_producto);
        //     $montoComision = $this->calcularMontoComisionPadre($product['total'], $porcentaje_comision_comercio_padre);

        //     $envio_propio = [];

        //     $item = new ItemPagopar();
        //     $item->name = $product['name'];
        //     $item->qty = $product['quantity'];
        //     $item->price = $product['total'];
        //     $item->cityId = ($city) ? $city : $this->seller_ciudad;
        //     $item->desc = $product['name'];
        //     $item->url_img = urldecode(get_the_post_thumbnail_url($p_id, 'medium'));
        //     if (is_numeric($this->configuracion_avanzada_id_categoria_defecto))
        //     {
        //         $item->category = $this->configuracion_avanzada_id_categoria_defecto;
        //     }
        //     else
        //     {
        //         $item->category = get_post_meta($p_id, 'pagopar_final_cat', true);
        //     }

        //     #$item->productId = $p_id;
        //     $item->productId = $idProductoReal;

        //     $item->sellerPhone = ($phone) ? $phone : $this->seller_phone;
        //     $item->sellerAddress = ($addr) ? $addr : $this->seller_addr;
        //     $item->sellerAddressRef = ($addr_ref) ? $addr_ref : $this->seller_addr_ref;
        //     $item->sellerAddressCoo = ($coo) ? $coo : $this->seller_coo;

        //     if ($splitBillingHabilitado === true)
        //     {
        //         $item->sellerPublicKey = $comercio_hijo_vendedor_producto;
        //     }
        //     else
        //     {
        //         $item->sellerPublicKey = $this->public_key;
        //     }

        //     $item->weight = ($weight) ? $weight : '';
        //     $item->large = ($largo) ? $largo : '';
        //     $item->width = ($ancho) ? $ancho : '';
        //     $item->height = ($alto) ? $alto : '';
        //     $item->retiroObs = ($retiro_obs) ? $retiro_obs : $this->sucursal_obs;
        //     if ($json_propio)
        //     {
        //         $propios = json_decode($json_propio);
        //         foreach ($propios as $propio)
        //         {
        //             $envio_propio[] = ["tiempo_entrega" => $propio[2], "destino" => $propio[0], "precio" => $propio[1], ];
        //         }
        //         $item->propio = $envio_propio;
        //     }

        //     if ($splitBillingHabilitado === true)
        //     {
        //         $item->comercio_comision = $montoComision;

        //     }

        //     $this
        //         ->pedidoPagopar
        //         ->order
        //         ->addPagoparItem($item);
        // }
        
        // Obtenemos costo de envio de Woocommerce


        // Obtenemos costo de impuesto de Woocommerce
         $order_fees = $order->get_fees();
        foreach ($order_fees as $fee)
        {
            $feeName = $fee['name'];
            $feeAmount = $fee['amount'];

            //$item = new ItemPagopar();
            //$item->name = $feeName;
            //$item->qty = 1;
            //$item->price = $feeAmount;
            //$item->cityId = ($city) ? $city : $this->seller_ciudad;
            //$item->desc = $feeName;
            //$item->url_img = '';
//
            //$item->category = 909;
            //$item->productId = $p_id;
            //$item->sellerPhone = ($phone) ? $phone : $this->seller_phone;
            //$item->sellerAddress = ($addr) ? $addr : $this->seller_addr;
            //$item->sellerAddressRef = ($addr_ref) ? $addr_ref : $this->seller_addr_ref;
            //$item->sellerAddressCoo = ($coo) ? $coo : $this->seller_coo;
            //$item->sellerPublicKey = $this->public_key;
            //$item->weight = '';
            //$item->large = '';
            //$item->width = '';
            //$item->height = '';
            //$item->retiroObs = '';
//
            //$this
            //    ->pedidoPagopar
            //    ->order
            //    ->addPagoparItem($item);
            $itemPagopar = array(
                "nombre"=>$feeName,
                "cantidad"=>1,
                "precio_total"=>$feeAmount,
                "ciudad"=>($city) ? $city : $this->seller_ciudad,
                "descripcion"=>$feeName,
                "url_imagen"=>'',
                "peso"=>'',
                "vendedor_telefono"=>($phone) ? $phone : $this->seller_phone,
                "vendedor_direccion"=>($addr) ? $addr : $this->seller_addr,
                "vendedor_direccion_referencia"=>($addr_ref) ? $addr_ref : $this->seller_addr_ref,
                "vendedor_direccion_coordenadas"=>($coo) ? $coo : $this->seller_coo,
                "public_key"=>$payments['pagopar']->settings['public_key'],
                "categoria"=>909,
                "id_producto"=>$p_id,
                "largo"=>'',
                "ancho"=>'',
                "alto"=>'',
                "opciones_envio" => null,
                "costo_envio" =>  floatval($monto_delivery),
                "envio_seleccionado" => $metodo_seleccionado
        );
            array_push($items_nuevo_pedido , $itemPagopar);
        }

        // Se obtienen los taxes configurados en Woocommerce - ajustes -impuestos
        $order_taxes = $order->get_tax_totals();

       foreach ($order_taxes as $tax)
        {
            $taxAmount = $tax->amount;
            $taxName = $tax->label;

            //$item = new ItemPagopar();
            //$item->name = $taxName;
            //$item->qty = 1;
            //$item->price = $taxAmount;
            //$item->cityId = ($city) ? $city : $this->seller_ciudad;
            //$item->desc = $feeName;
            //$item->url_img = '';
//
            //$item->category = 909;
            //$item->productId = "tax_rate_id-" . $tax->rate_id;
            //$item->sellerPhone = ($phone) ? $phone : $this->seller_phone;
            //$item->sellerAddress = ($addr) ? $addr : $this->seller_addr;
            //$item->sellerAddressRef = ($addr_ref) ? $addr_ref : $this->seller_addr_ref;
            //$item->sellerAddressCoo = ($coo) ? $coo : $this->seller_coo;
            //$item->sellerPublicKey = $this->public_key;
            //$item->weight = '';
            //$item->large = '';
            //$item->width = '';
            //$item->height = '';
            //$item->retiroObs = '';
//
            //$this
            //    ->pedidoPagopar
            //    ->order
            //    ->addPagoparItem($item);

        $itemPagopar = array(
                "nombre"=>$taxName,
                "cantidad"=>1,
                "precio_total"=>$taxAmount,
                "ciudad"=>($city) ? $city : $this->seller_ciudad,
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

        $newOrderPagopar['compras_items'] = $items_nuevo_pedido;
        $newOrderPagopar['public_key'] = $payments['pagopar']->settings['public_key'];

        // $this
        //     ->pedidoPagopar
        //     ->order->privateKey = $this->private_key;
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
        //    ->order->periodOfDaysForPayment = $this->periodOfDaysForPayment;
        //$this
        //    ->pedidoPagopar
        //    ->order->periodOfHoursForPayment = (int)$this->periodOfHoursForPayment;
        //$this
        //    ->pedidoPagopar
        //    ->order->desc = ""; #$order->customer_note;

        date_default_timezone_set("America/Asuncion");
        //Transformamos el día a horas
        $daysToHours = ($this->periodOfDaysForPayment) ? ($this->periodOfDaysForPayment * 24) : 0;
        $date = date("Y-m-d H:i:s", mktime(date("H") + $this->periodOfHoursForPayment + $daysToHours, date("i"), date("s"), date("m"), date("d"), date("Y")));
        $newOrderPagopar['fecha_maxima_pago'] = $date;
        $newOrderPagopar['descripcion_resumen'] = "";
        //$json_flete = $this
        //    ->pedidoPagopar
        //    ->getMethodsOfShipping();
        
        $json = get_post_meta($order_id, 'pagopar_json_selected', true);
        $this->pagopar_actualizar_orden_envio($order_id);

        
        $customer_order = new WC_Order((int)$order_id);

        # Actualizamos los valores de razon social /  ruc
        $customer_order->update_meta_data($nombreCampoRazonSocial, $buyer->socialReason);
        $customer_order->update_meta_data($nombreCampoRuc, $buyer->ruc);

        #$customer_order->save;
        // Mark order as Paid
        #$customer_order->payment_complete();
        // Empty the cart (Very important step)
        $emptyCartPagopar = $payments['pagopar']->settings['disabled_clear_cart'];

        global $current_user;
        get_currentuserinfo();

        $user_id = $current_user->ID;
        
        
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
        
        $customer_order->update_status($estadoPagopar, 'Procesando pedido (No pagado).');
        
        
        #echo json_encode($newOrderPagopar); die();

        $resultado = $this->runCurl($newOrderPagopar, 'https://api-plugins.pagopar.com/api/comercios/1.1/iniciar-transaccion');
        $response = json_decode($resultado);
        if ($response->respuesta === false)
        {
            wc_add_notice('Ocurrió un error al realizar la transacción: ' . $response->resultado, 'error');
            return null;
        }
        $hashOrder = $response->resultado[0]->data;
        return $hashOrder;
}

function calculate_flete_suscription($ciudad_id, $newOrderId, $userId)
{

    $payments = WC()
        ->payment_gateways
        ->payment_gateways();

    global $woocommerce;
    $orderPagopar = array();
    $order = WC()->cart->get_cart();
    $orderPagopar['tipo_pedido'] = "VENTA-COMERCIO";
    $orderPagopar['fecha_maxima_pago'] = "2020-05-08 14:01:00";
    $orderPagopar['public_key'] = $payments['pagopar']->settings['public_key'];
    $orderPagopar['id_pedido_comercio'] = 1;
    $orderPagopar['monto_total'] = round(WC()->cart->get_cart_contents_total(), 0);
    $orderPagopar['token'] = sha1($payments['pagopar']->settings['private_key']."1".$order->total);
    $orderPagopar['descripcion_resumen'] = "";
    $orderPagopar['comprador'] = array(
        "nombre" => "Rudolph Goetz",
        "ciudad"=>$ciudad_id,
        "email"=>"fernandogoetz@gmail.com",
        "telefono"=>"0972200046",
        "tipo_documento"=>"CI",
        "documento"=>"4247903",
        "direccion"=>"Rafael Barret casi Conradi",
        "direccion_referencia"=>"",
        "coordenadas"=>"-25.26080770331157, -57.51165674656511",
        "ruc"=>null,
        "razon_social"=>null
    );
    $items = [];

    $old_order = wc_get_order($newOrderId);
    $old_order_detail = new WC_Order($newOrderId); 

    $metodo_propio= [];
    
    
    
    # Hay que descontar del monto total sino no va a cuadrar la sumatoria de los items para enviar a Pagopar
    $totalDescuento = intval(WC()->cart->get_discount_total());
    $totalDescuentoRestante = intval($totalDescuento);
    
    foreach ($old_order->get_items() as $item_id => $orderitem ) {
        
        $_product =  wc_get_product($orderitem->get_product_id());
        $product_id = $orderitem->get_product_id();
        $price = get_post_meta($product_id, '_price', true);
        
        $quantity = $orderitem->get_quantity();
        $subtotal = $price * $quantity;
        $total = $total + $subtotal;
        
        # Parche mientras se habilite descuento en API 2.0 en Pagopar
        $descripcionDescuento = '';
        # SI hay un descuento pendiente por restar para que cuadren los números
        if ($totalDescuentoRestante > 0){
            $subtotal = $subtotal - $totalDescuentoRestante;
            # Para ver si se necesita volver a descontar en el siguiente item
            $totalDescuentoRestante = $subtotal - $totalDescuentoRestante;
            $descripcionDescuento = ' menos descuento';
       
        }
        
        
        
        //$link = $product->get_permalink($product);
        // Anything related to $product, check $product tutorial
        //$meta = wc_get_formatted_cart_item_data($product);

        //Verificamos si el producto es virtual o descargable
        $isVirtual = get_post_meta($product_id, '_virtual', true) === "yes";
        $isDownloable = get_post_meta($product_id, '_downloadable', true) === "yes";

        $post_type = get_post_field('post_type', $product_id);
        $cat = get_post_meta($product_id, 'pagopar_final_cat', true);
        #$city_id = get_post_meta($product_id, 'product_seller_ciudad', true);
        
        $pagopar_direccion_id_woocommerce =  get_post_meta($product_id, 'pagopar_direccion_id_woocommerce', true);
        $direccionProducto = traerDirecciones($pagopar_direccion_id_woocommerce);
        $city_id = $direccionProducto[0]->id;

        $idProductoPadre = $product_id;

        $url = urldecode(get_the_post_thumbnail_url($product_id, 'medium'));

        //Validamos que el producto es una variante o un producto padre
        if ($post_type === 'product_variation') {
            $parent_id = get_post_field('post_parent', $product_id);
            $idProductoPadre = $parent_id;
            
            $urlBk = urldecode(get_the_post_thumbnail_url($parent_id, 'medium'));
            
            if (trim($urlBk)!==''){
                $url = $urlBk;
            }
            

            $pagopar_direccion_id_woocommerce =  get_post_meta($parent_id, 'pagopar_direccion_id_woocommerce', true);
            $direccionProducto = traerDirecciones($pagopar_direccion_id_woocommerce);
            $city_id = $direccionProducto[0]->id;
        }
        
        $cat = get_post_meta($idProductoPadre, 'pagopar_final_cat', true);
        $weight = get_post_meta($idProductoPadre, 'product_weight', true);
        $largo = get_post_meta($idProductoPadre, 'pagopar_largo', true);
        $ancho = get_post_meta($idProductoPadre, 'pagopar_ancho', true);
        $alto = get_post_meta($idProductoPadre, 'pagopar_alto', true);            
        

        # Si no se definieron los valores categoria, alto, largo, ancho o peso, se intenta tomar de los valores generales de woocommerce
        if ((!is_numeric($alto)) or (!is_numeric($largo)) or (!is_numeric($ancho)) or (!is_numeric($weight)) or (!is_numeric($cat))){
            $weight = get_post_meta($idProductoPadre, '_weight', true);            
            $largo = get_post_meta($idProductoPadre, '_length', true);
            $ancho = get_post_meta($idProductoPadre, '_width', true);
            $alto = get_post_meta($idProductoPadre, '_height', true);   
            $cat = 979;
        }
        
        
        

        # aqui hacer que tome valores de peso y demas generico

        
        $metodo_propio= [];
        
        $pagopar_envio_aex_pickup_horario_fin = intval(get_post_meta($idProductoPadre, 'pagopar_envio_aex_pickup_horario_fin', true)); 
        if ($pagopar_envio_aex_pickup_horario_fin===0){
            $pagopar_envio_aex_pickup_horario_fin = 48;
        }
        # Armamos envio propio
        foreach($rates as $rate_key => $rate){
            # Los flat_rate  de Woocommerce son los que consideramos como envio propio en Pagopar
            if ($rates[$rate_key]->method_id === 'flat_rate'){
                $propio = array(
                  "tiempo_entrega" => $pagopar_envio_aex_pickup_horario_fin,
                  "destino" => $ciudad_id,
                  "precio" => floatval($rates[$rate_key]->cost)
                );
                array_push($metodo_propio, $propio);
            }
            
        }
        
        # Armamos recogida del local
        $retiroSucursal = [];
        foreach($rates as $rate_key => $rate){
            # Los local_pickup de Woocommerce son los que consideramos como retiro de sucursal en Pagopar
            if ($rates[$rate_key]->method_id === 'local_pickup'){
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
        $mobi['horarios'][0]['dias'] = array("1", "2", "3", "4", "5");    
        $mobi['horarios'][0]['pickup_fin'] = "18:00";    
        $mobi['horarios'][0]['pickup_inicio'] = "08:00";    
        
        $itemPagopar = array(
            "nombre"=>$_product->get_title(),
            "cantidad"=>$quantity,
            "precio_total"=>$subtotal,
            "ciudad"=>$city_id,
            "descripcion"=>$_product->get_title() . $descripcionDescuento,
            "url_imagen"=>$url,
            "peso"=>$weight,
            "vendedor_telefono"=>"0972200046",
            "vendedor_direccion"=>"Avda euseio ayala N 1146 entre 26 de febrero y 33 orientales",
            "vendedor_direccion_referencia"=>"Observacion.",
            "vendedor_direccion_coordenadas"=>"-25.3030957,-57.61316349999999",
            "public_key"=>$payments['pagopar']->settings['public_key'],
            "categoria"=>($isVirtual || $isDownloable) ? 909 :$cat,
            "id_producto"=>$product_id,
            "largo"=>$largo,
            "ancho"=>$ancho,
            "alto"=>$alto,
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
    }
    $orderPagopar['compras_items'] = $items;

    $jsonObject = array();
    $jsonObject['token'] = sha1($payments['pagopar']->settings['private_key']."CALCULAR-FLETE");
    $jsonObject['token_publico'] = $payments['pagopar']->settings['public_key'];
    $jsonObject['dato'] = json_encode($orderPagopar);
    
    
    $args = json_encode($jsonObject);

    $ch = curl_init();
    $headers = array('Accept: application/json', 'Content-Type: application/json', 'X-Origin: Woocommerce');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, "https://api-plugins.pagopar.com/api/calcular-flete/1.1/traer");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $args);

    $response = curl_exec($ch);
    

    curl_close($ch);
    
    return json_decode($response);
}