<?php
    class AdminHelpers {

        public function getAdminFields($cities_wc_format, $comerciosHijosJson, $estadosExistentesPedido, $formasPagoJson, $formasPagoFechaActualizacion, $horarios, $page_confirm_url_pagopar, $page_gracias_pagopar) {
            
            return array(
                'enabled' => array(
                    'title' => __('Activar / Desactivar', 'pagopar'),
                    'label' => __('Habilitar el gateway de Pagopar', 'pagopar'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ),
                'title' => array(
                    'title' => __('Título', 'pagopar'),
                    'type' => 'text',
                    'description' => __('Título del método de pago que el comprador verá durante el proceso de checkout', 'pagopar'),
                    'default' => __('', 'pagopar'),
                    'desc_tip' => true,
                    'placeholder' => __('Opcional', 'pagopar'),
                ),
                'description' => array(
                    'title' => __('Descripción', 'pagopar'),
                    'type' => 'text',
                    'description' => __('Descripción del método de pago que el comprador verá durante el proceso de checkout', 'pagopar'),
                    'default' => __('', 'pagopar'),
                    'desc_tip' => true,
                    'placeholder' => __('Opcional', 'pagopar'),
                ),
                'public_key' => array(
                    'title' => __('Clave Pública', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => '',
                ),
                'private_key' => array(
                    'title' => __('Clave Privada', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => '',
                ),
                'periodOfDaysForPayment' => array(
                    'title' => __('Periodo de días para el pago', 'pagopar'),
                    'type' => 'numeric',
                    'desc_tip' => __('Por default es un día. Los días se transforman en horas en el cálculo final', 'pagopar'),
                    'default' => 2,
                ),
                'periodOfHoursForPayment' => array(
                    'title' => __('Periodo de horas para el pago', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('Por default es cero horas. Si se ingresa, se suma a los días', 'pagopar'),
                    'default' => '0',
                ),
                
                'url_details' => array(
                    'title' => __('Datos de URLs', 'pagopar'),
                    'type' => 'title',
                    'description' => __('', 'pagopar'),
                ),
                'url_thankyou' => array(
                    'title' => __('URL de redireccionamiento', 'pagopar'),
                    'type' => 'parragraph',
                    'description' => $page_gracias_pagopar,
                    'desc_tip' => false,
                ),
                'url_respuesta' => array(
                    'title' => __('Url de Respuesta', 'pagopar'),
                    'type' => 'parragraph',
                    'description' => $page_confirm_url_pagopar,
                    'desc_tip' => false,
                ),                
                         
                'sucursal_title' => array(
                    'title' => __('Retiro de Sucursal', 'pagopar'),
                    'type' => 'title',
                    'description' => __('', 'pagopar'),
                ),
                /*'enabled_retiro' => array(
                    'title' => __('Activar / Desactivar Retiro del sucursal', 'pagopar'),
                    'label' => __('Habilitar el retiro de sucursal. Para solicitar esta opción comuníquese con <a href="mailto:soporte@pagopar.com">soporte@pagopar.com</a>', 'pagopar'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ),*/
                'disabled_clear_cart' => array(
                    'title' => __('Borrar carrito al finalizar compra - funcionalidad beta, recomendamos habilite esta opción', 'pagopar'),
                    'label' => __('', 'pagopar'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                ),
                'sucursal_obs' => array(
                    'title' => __('Observaciones', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => '',
                ),

                'configuracion_avanzada' => array(
                    'title' => __('Configuración Avanzada', 'pagopar'),
                    'type' => 'title',
                    'description' => __('', 'pagopar'),
                ),
                'configuracion_avanzada_id_categoria_defecto' => array(
                    'title' => __('¿Vás a utilizar el servicio de AEX de Pagopar? (Use bajo previa explicación)', 'pagopar'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'options' => array(
                        ''    => _x( 'Si, voy a utilizar delivery de AEX y ya me explicaron sobre su funcionamiento', 'Id Categoria por defecto', 'pagopar' ),
                        '909' => _x( 'No, no voy a utilizar delivery de AEX', 'Id Categoria por defecto', 'pagopar' ),
                    ),
                    'default' => '909',
                ),
                /*'mostrar_siempre_todos_medios_pago' => array(
                    'title' => __('Mostrar todos los medios de pago sin importar los montos mínimos', 'pagopar'),
                    'label' => __('El comercio asume los montos mínimos de los medios de pago previa autorización en el sistema Pagopar. <br />Para más explicación contactar a <a href="mailto:soporte@pagopar.com">soporte@pagopar.com</a>', 'pagopar'),
                    'type' => 'checkbox',
                    'default' => 'yes',
                ),*/
                'mostrar_medios_pago_pagopar' => array(
                    'title' => __('Mostrar todos los medios de pago en WooCommerce (Use bajo previa explicación)', 'pagopar'),
                    'label' => __('Se muestran los medios de pago en el checkout de WooCommerce y se habilita el redireccionamiento automático a los medios de pago finales. <br />Para solicitar esta opción comuníquese con <a href="mailto:soporte@pagopar.com">soporte@pagopar.com</a>', 'pagopar'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ),
                'pagopar_metodos_envios_a_mostrar' => array(
                    'title' => __('Opciones de Envío a mostrar (Se recomienda seleccionar todos los medios de envío)', 'pagopar'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'options' => array(
                        '0'    => _x( 'Todos', 'Id Categoria por defecto','pagopar'),
                        '11' => _x( 'AEX', 'Id Categoria por defecto','pagopar'),
                        '3' => _x( 'Estándar', 'Id Categoria por defecto','pagopar'),
                        '5' => _x( 'Locker', 'Id Categoria por defecto','pagopar'),
                    ),
                    'default' => '0',
                ),
                'prefijo_orden_pedido' => array(
                    'title' => __('Prefijo de Identificador del Pedido (Use bajo previa explicación)', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => '',
                ),
                'campos_alternativos' => array(
                    'title' => __('Personalización de campos en Checkout (*Use bajo previa explicación)', 'pagopar'),
                    'type' => 'title',
                    'description' => __('', 'pagopar'),
                ),
                'usar_formulario_minimizado' => array(
                    'title' => __('Utilizar formulario minimizado (elimina campos sugeridos por Pagopar)', 'pagopar'),
                    'type' => 'checkbox',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => 'yes',
                ),
                'eliminar_campo_pais' => array(
                    'title' => __('Eliminar campo país', 'pagopar'),
                    'type' => 'checkbox',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => 'yes',
                ),
                'campo_alternativo_documento' => array(
                    'title' => __('Identificador del campo Documento (No ingrese su CI)', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => 'billing_documento',
                ),
                'campo_alternativo_razon_social' => array(
                    'title' => __('Identificador del campo Razón social  (No ingrese su Razon Social)', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => 'billing_razon_social',
                ),
                'campo_alternativo_ruc' => array(
                    'title' => __('Identificador del campo RUC  (No ingrese su RUC)', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => 'billing_ruc',
                )


            ,
                'valores_defectos_tributacion' => array(
                    'title' => __('Valores por defecto', 'pagopar'),
                    'type' => 'title',
                    'description' => __('', 'pagopar'),
                ),
                'valor_defecto_razon_social' => array(
                    'title' => __('Valor por defecto del campo Razón social', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => '',
                ),
                'valor_defecto_ruc' => array(
                    'title' => __('Valor por defecto del campo RUC', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => '',
                ),




                'split_billing_titulo' => array(
                    'title' => __('Split Billing', 'pagopar'),
                    'type' => 'title',
                    'description' => __('', 'pagopar'),
                ),
                'habilitar_split_billing' => array(
                    'title' => __('Habilitar Split Billing', 'pagopar'),
                    'label' => __('Para solicitar esta opción comuníquese con <a href="mailto:soporte@pagopar.com">soporte@pagopar.com</a>', 'pagopar'),
                    'type' => 'checkbox',
                    'default' => 'no',
                ),
                'porcentaje_comision_comercio_padre' => array(
                    'title' => __('Porcentje de comisión del Comercio Padre', 'pagopar'),
                    'type' => 'text',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => '0',
                ),'json_comercio_hijos' => array(
                    'title' => __('', 'pagopar'),
                    'type' => 'hidden',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => $comerciosHijosJson,
                ),
                'estados_pedidos_pagopar' => array(
                    'title' => __('Estados de Pedidos', 'pagopar'),
                    'type' => 'title',
                    'description' => __('', 'pagopar'),
                ),
                'estado_creacion_pedido_pagopar' => array(
                    'title' => __('Estado al crearse el pedido', 'pagopar'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'options' => $estadosExistentesPedido,
                    'default' => 'wc-pending'
                ),
                'estado_pagado_pedido_pagopar' => array(
                    'title' => __('Estado al pagarse el pedido', 'pagopar'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'options' => $estadosExistentesPedido,
                    'default' => 'wc-completed'
                ),
                'json_forma_pago' => array(
                    'title' => __('', 'pagopar'),
                    'type' => 'hidden',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => $formasPagoJson,
                ),
                'json_forma_pago_fecha_actualizacion' => array(
                    'title' => __('', 'pagopar'),
                    'type' => 'hidden',
                    'desc_tip' => __('', 'pagopar'),
                    'default' => $formasPagoFechaActualizacion,
                )
            );
        }
    }
    //wc-processing