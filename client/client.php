<?php

    class Client {
        public function getThankYouPage($content) {
            $output = $content;

            $hash_url = $_GET['hash'];

            if ($hash_url)
            {

                global $wpdb;
                $order_db = $wpdb->get_results($wpdb->prepare("SELECT * FROM wp_transactions_pagopar WHERE hash = %s ORDER BY id DESC LIMIT 1", $hash_url));
                $pp_order = $order_db[0];
                
                # Importante: El ID de orden modificado se usa en algunas funciones y el ID de orden real en otras (Compatibilidad con el plugin ooCommerce Sequential Order Numbers Pro)
                $ordenPedidoNormal = $pp_order->id;

				
                # Agregamos compatibilidad al plugin ooCommerce Sequential Order Numbers Pro, obtenemos el ID real del post
                $ordenIDReal = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM ".$wpdb->prefix."postmeta where meta_key = '_order_number' and meta_value = %s limit 1", $pp_order->id));
                $ordenIDReal = $ordenIDReal[0];
                if (is_numeric($ordenIDReal->post_id)){
                        $pp_order->id = $ordenIDReal->post_id;
                }
				
				
                if (isset($pp_order->id))
                {

                    $order_id = $pp_order->id;
                    $customer_order = new WC_Order((int)$order_id);

                    $downloads = $customer_order->get_downloadable_items();
                    $db = new DBPagopar(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, "wp_transactions_pagopar");
                    $pedidoPagopar = new Pagopar(null, $db, $origin_pagopar);
                    $payments = WC()
                        ->payment_gateways
                        ->payment_gateways();
                    $pedidoPagopar->publicKey = $payments['pagopar']->settings['public_key'];
                    $pedidoPagopar->privateKey = $payments['pagopar']->settings['private_key'];
                    $documentoAlternativo = $payments['pagopar']->settings['campo_alternativo_documento'];                    
                    $response = $pedidoPagopar->getPagoparOrderStatus((int)$ordenPedidoNormal);
                    $respuesta = json_decode($response, true);
										
                    if ($respuesta['respuesta'])
                    {
                        $datos = $respuesta['resultado'][0];

                        #$datos['forma_pago_identificador'] = 2; #simulamos pago con X medio
                        #$datos['pagado'] = false; #simulamos pago con X medio
                        $user = wp_get_current_user();
                        $documentoDefecto = get_user_meta($user->id, 'pagopar_documento', true);
						
                        
                        # SI no está logueado, obtenemos el usuario por el hash del pedido
                        #if ($user->id==0){
							

                            # Si se definio un campo alternativo para documento, usamos ese
                            if (($documentoAlternativo != '') and ($documentoAlternativo != 'billing_documento'))
                            {
                                $nombreCampoDocumento = $documentoAlternativo;
                            }
                            else
                            {
                                $nombreCampoDocumento = 'billing_documento';
                            }
                            $documentoDefecto = get_post_meta($order_id, $nombreCampoDocumento, true);
                        #}
				

                        $pagado = ($datos['pagado']) ? "Pedido pagado" : "Pendiente de pago";
                        $fechaMaximaPago = formatoFechaLatina($datos['fecha_maxima_pago']);
                        $fechaPago = formatoFechaLatina($datos['fecha_pago']);

                        if ($datos['pagado'] === true)
                        {
                            $output .= "<h2>¡Gracias por su compra!</h2>";
                            $output .= "<p>Hemos recibido su pago y enviado un resumen de su pedido a su e-mail.</p>";

                            if (isset($downloads)) {
                                $output .= '<section class="woocommerce-order-downloads">
                                            
                                            
                                            <table class="woocommerce-table woocommerce-table--order-downloads shop_table shop_table_responsive order_details">
                                            <thead>
                                                <tr>';
                                foreach ( wc_get_account_downloads_columns() as $column_id => $column_name ) {
                                    $output .= '<th class="'.esc_attr( $column_id ).'"><span class="nobr">'.esc_html( $column_name ).'</span></th>';
                                }
                                $output .= '</tr>
                                        </thead>';
                                foreach ( $downloads as $download ) {
                                    $output .= '<tr>';
                                    foreach ( wc_get_account_downloads_columns() as $column_id => $column_name ) {
                                        $output .= '<td class="'.esc_attr( $column_id ).'" data-title="'.esc_attr( $column_name ).'">';
                                        switch ( $column_id ) {
                                            case 'download-product':
                                                if ( $download['product_url'] ) {
                                                    $output .= '<a href="' . esc_url( $download['product_url'] ) . '">' . esc_html( $download['product_name'] ) . '</a>';
                                                } else {
                                                    $output .= esc_html( $download['product_name'] );
                                                }
                                                break;
                                            case 'download-file':
                                                $output .= '<a href="' . esc_url( $download['download_url'] ) . '" class="woocommerce-MyAccount-downloads-file button alt">' . esc_html( $download['download_name'] ) . '</a>';
                                                break;
                                            case 'download-remaining':
                                                $output .= is_numeric( $download['downloads_remaining'] ) ? esc_html( $download['downloads_remaining'] ) : esc_html__( '&infin;', 'woocommerce' );
                                                break;
                                            case 'download-expires':
                                                if (!empty( $download['access_expires'])) {
                                                    $output .= '<time datetime="' . esc_attr( date( 'Y-m-d', strtotime( $download['access_expires'] ) ) ) . '" title="' . esc_attr( strtotime( $download['access_expires'] ) ) . '">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $download['access_expires'] ) ) ) . '</time>';
                                                } else {
                                                    $output .= '<p>'.esc_html__( 'Never', 'woocommerce' ).'</p>';
                                                }
                                                break;
                                        }
                                        $output .= '</td>';
                                    }
                                    $output .= '</tr>';
                                }
                                $output .= '</table>';

                                $output .= '  </section>';
                            }
                        }
                        else
                        {
                            if (in_array($datos['forma_pago_identificador'], array(1,9,10,12,15,16,18, 20, 23, 25,26)))
                            {
                                $output .= "<h2>¡Hubo un error al intentar pagar el pedido!</h2>";
                                if (in_array($datos['forma_pago_identificador'], array(1,9,26)))
                                {
                                    $output .= "<ul><strong>¿Porqué no se realizó el pago? Algunos de los errores más comunes son:</strong>:";
                                    $output .= "<li>Intentó pagar con la misma tarjeta un mismo monto en menos de 5 minutos. Para evitar duplicación de pagos, se evita el pago con la misma tarjeta de un mismo monto. No obstante, ya puede volver a probar el pago y será procesado.</li>";
                                    $output .= "<li>La tarjeta no tiene fondos suficientes</li>";
                                    $output .= "<li>La tarjeta no está habilitada para compras en Internet</li>";
                                    $output .= "<li>Se ingresó incorrectamente el código CVV</li>";
                                    $output .= "</ul>";
                                    $output .= "<p><em class='consultarBanco'>Para saber más detalle sobre porqué no pudo realizarse el pago puede comunicarse con la entidad financiera emisora de su tarjeta</em><p>";
                                }
                                elseif (in_array($datos['forma_pago_identificador'], array(24)))
                                {
                                    $output .= "<ul><strong>¿Porqué no se realizó el pago? Algunos de los errores más comunes son:</strong>:";
                                    $output .= "<li>No tiene fondos suficientes</li>";
                                    $output .= "<li>La tarjeta seleccionada no está habilitada para compras con QR</li>";
                                    $output .= "</ul>";
                                    $output .= "<p><em class='consultarBanco'>Para saber más detalle sobre porqué no pudo realizarse el pago puede comunicarse con la entidad financiera emisora de su tarjeta</em><p>";
                                }
                                elseif (in_array($datos['forma_pago_identificador'], array(25)))
                                {
                                    $output .= "<strong>¿Porqué no se realizó el pago?</strong>:";
                                    $output .= $datos['mensaje_resultado_pago']['descripcion'];
                                }
                                elseif (in_array($datos['forma_pago_identificador'], array(10,12,20, 23)))
                                {
                                    $output .= "<ul><strong>¿Porqué no se realizó el pago? Algunos de los errores más comunes son:</strong>:";
                                    $output .= "<li>No tiene fondos suficientes en su billetera</li>";
                                    $output .= "<li>Se ingresó incorrectamente el PIN de transacción de su billetera</li>";
                                    $output .= "</ul>";
                                    $output .= "<p><em class='consultarBanco'>Para saber más detalle sobre porqué no pudo realizarse el pago puede comunicarse con su empresa de telefonía proveedora de su billetera.</em><p>";
                                }
                            }elseif (in_array($datos['forma_pago_identificador'], array(11))){
                                $output .= "<h2>Hemos recibido su pedido de pago</h2>";
                                $output .= "<p>El pago se procesa en días hábiles de 08:30 a 17:30. Fuera de este horario, el pago se concretará el día siguiente hábil. Cuando se procese el pago, le avisaremos vía e-mail.</p>";
                            }
                            else
                            {
                                $output .= "<h2>Hemos recibido su pedido de pago</h2>";
                            }

                            if ($datos['forma_pago_identificador'] == 7)
                            {
                                $output .= "<p>Debe ingresar a su Homebanking, y buscar en el apartado 'Pago de Servicios' el comercio <strong>PAGOPAR</strong>, ingresando su cédula <strong>" . formatoEnteroString($documentoDefecto) . "</strong> o número de pedido <strong>" . formatoEnteroString($datos['numero_pedido']) . "</strong>.</p>";

                                $output .= "<ul><strong>Las entidades financieras habilitadas para el pago desde el Homebanking son:</strong>";
                                $output .= "<li><a target='_blank' href='https://www.visionbanco.com/' class='btn-link'>Visión Banco</a></li>";
                                $output .= "<li><a target='_blank' href='https://www.bancoatlas.com.py' class='btn-link'>Banco Atlas</a></li>";
                                $output .= "<li><a target='_blank' href='https://www.bancognb.com.py/' class='btn-link'>Banco GNB</a></li>";
                                $output .= "<li><a target='_blank' href='https://www.interfisa.com.py/' class='btn-link'>Banco Interfisa</a></li>";
                                $output .= "<li><a target='_blank' href='https://www.bancoitapua.com.py/' class='btn-link'>Banco Itapúa</a></li>";
                                $output .= "<li><a target='_blank' href='https://www.regional.com.py/' class='btn-link'>Banco Regional</a></li>";
                                $output .= "<li><a target='_blank' href='http://www.bancop.com.py/' class='btn-link'>Banco Bancop</a></li>";
                                $output .= "<li><a target='_blank' href='https://www.bancobasa.com.py/' class='btn-link'>Banco BASA</a></li>";
                                $output .= "<li><a target='_blank' href='http://www.cu.coop.py/' class='btn-link'>Cooperativa Universitaria</a></li>";
                                $output .= "</ul>";
                            }
                            elseif (in_array($datos['forma_pago_identificador'], array(22,2,3,4)))
                            {
                                $output .= '<ul>';
                                $output .= '<li>  Eligió pagar con ' . $datos['forma_pago'] . ', recuerde que tiene hasta las ' . $fechaMaximaPago['hora'] . ' del ' . $fechaMaximaPago['fecha'] . ' para pagar.</li>';
                                $output .= '<li>  Debe ir a boca de cobranza de ' . $datos['forma_pago'] . ', decir que quiere pagar el comercio <strong style="font-size:16px;color:#0f68a8">Pagopar</strong>, mencionando su cédula <strong>' . formatoEnteroString($documentoDefecto) . '</strong> o número de pedido <strong>' . formatoEnteroString($datos['numero_pedido']) . '</strong>.</li>';
                                $output .= '</ul>';

                                if ($datos['forma_pago_identificador'] == 22)
                                {
                                    $output .= '<div style="float:right;">';
                                    $output .= '<a target="_blank" href="https://www.wepa.com.py/" class="btn-link">Ver bocas de cobranzas de ' . $datos['forma_pago'] . '</a>';
                                    $output .= '</div>';
                                    $output .= '<div style="clear:both;"></div>';
                                }elseif ($datos['forma_pago_identificador'] == 2)
                                {
                                    $output .= '<div style="float:right;">';
                                    $output .= '<a target="_blank" href="http://www.pronet.com.py/quepago/" class="btn-link">Ver bocas de cobranzas de ' . $datos['forma_pago'] . '</a>';
                                    $output .= '</div>';
                                    $output .= '<div style="clear:both;"></div>';
                                }
                                elseif ($datos['forma_pago_identificador'] == 3)
                                {
                                    $output .= '<div style="float:right;">';
                                    $output .= '<a target="_blank" href="http://www.pagoexpress.com.py:81/v4/bocas.php" class="btn-link">Ver bocas de cobranzas de ' . $datos['forma_pago'] . '</a>';
                                    $output .= '</div>';
                                    $output .= '<div style="clear:both;"></div>';
                                }
                                elseif ($datos['forma_pago_identificador'] == 4)
                                {
                                    $output .= '<div style="float:right;">';
                                    $output .= '<a target="_blank" href="https://www.documenta.com.py/bocas.php" class="btn-link">Ver bocas de cobranzas de ' . $datos['forma_pago'] . '</a>';
                                    $output .= '</div>';
                                    $output .= '<div style="clear:both;"></div>';
                                }
                            }
                        }

                        $output .= "<div class='cuadroResumen'>";
                        $output .= "<h4>Datos del pedido:</h4>";

                        $output .= "<p><strong>Número de pedido de pago:</strong> " . formatoEnteroString($datos['numero_pedido']) . " " . "</p>";
                        $output .= "<p><strong>Forma de pago:</strong> " . $datos['forma_pago'] . "</p>";
                        $output .= "<p><strong>Estado del pago:</strong> " . $pagado . "</p>";
                        #$fecha = ($datos['fecha_pago']) ? $datos['fecha_pago'] : '';
                        if ($datos['pagado'] === true)
                        {
                            $output .= "<p><strong>Fecha de pago:</strong> " . $fechaPago['fecha'] . ' ' . $fechaPago['hora'] . "</p>";
                        }
                        $symbol = get_woocommerce_currency_symbol();
                        $output .= "<p><strong>Monto:</strong> " . wc_price($datos['monto']) . " " . "</p>";
                        #$output .= "<p><strong>Fecha máxima de pago:</strong> " . $datos['fecha_maxima_pago'] . "</p>";
                        $output .= "</div>";
                    }
                    if ($respuesta['cancelado'] === true)
                    {
                        $output .= "<h3>Existe un problema con el pedido.<h3>";
                        $output .= "<p>Fallo del pedido, motivo: " . $respuesta['resultado'][0] . "</p>";

                        global $woocommerce;
                        $customer_order = new WC_Order((int)$order_id);
                        $customer_order->add_order_note('Fallo del pedido, Motivo: ' . $respuesta['resultado'][0] . '.');

                        #$customer_order->update_status('failed');
                        $customer_order->update_status('cancelled');
                        /* wp_redirect($customer_order->get_cancel_order_url_raw()); */
                    }
                }
                else
                {
                    $output .= '<h3>Existe un error con el hash</h3>';
                }
            }
            else
            {
                $output .= '<h3>Existe un error con el hash</h3>';
            }
            $output = '<div class="estadoPedidoPagopar">' . $output . '</div>';

            $output .= '
<style>
.cuadroResumen {background: #fcfcfc;
    border: solid 1px #eee;
    padding: 15px;
    line-height: 14px;
    font-size: 15px;margin-top:15px;}

.consultarBanco {font-size:14px;}

</style>

        ';

            return $output;
        }
    }