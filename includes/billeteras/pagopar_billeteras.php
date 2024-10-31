<?php
ini_set('display_errors', 'true');
error_reporting(0);


class Pagopar_Billeteras extends WC_Payment_Gateway {
   
    
    /**
    * Constructor
    */
    function __construct() {
        // Recovers the necessary functions
        

        $this->id = 'pagopar_billeteras';
        $this->icon = '"width=50px"';
        
        $this->has_fields = false;
        $this->method_title = 'Billeteras Electrónicas';
        $this->title = "Billeteras Electrónicas";
        // Load the form fields
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();

        // Get setting values
        

    

        // Hooks
        add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt'));
        add_action('woocommerce_api_' . $this->id, array($this, 'api'));
    } //end __construct()

    /**
    * Admin tools
    */
     public function get_description() {
            $urlBasePlugin = plugin_dir_url(dirname(dirname(__FILE__)));

            $tema = 'dark';
            $description_html = '';
            $idOrdenPedidoYaCreado = get_query_var('order-pay');
            if (is_numeric($idOrdenPedidoYaCreado))
            {
                $description_html = ' <input id="billing_metodo_pago" type="hidden" name="billing_metodo_pago" value="">';
            }
            $description_html .= sprintf('
                    <ul class="pagopar_payments">
                        <li>
                            <label for="sub_payment_method3">
                                <input id="sub_payment_method3" type="radio" class="input-radio" name="modopago" value="10" data-order_button_text="">
                                Tigo Money
                            </label>
                            <span class="methods_group">
                                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/tigo-money.png" alt=""></span>
                            </span>
                        </li>
                        <li>
                            <label for="sub_payment_method4">
                                <input id="sub_payment_method4" type="radio" class="input-radio" name="modopago" value="12" data-order_button_text="">
                                Billetera Personal
                            </label>
                            <span class="methods_group">
                                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/billetera-personal.png" alt=""></span>
                            </span>
                        </li>
                        <li>
                            <label for="sub_payment_method5">
                                <input id="sub_payment_method5" type="radio" class="input-radio" name="modopago" value="18" data-order_button_text="">
                                Zimple
                            </label>
                            <span class="methods_group">
                                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/zimple.png" alt=""></span>
                            </span>
                        </li>
                    </ul>
                    <p class="pagopar-copy">Procesado por Pagopar <img src="'.$urlBasePlugin.'images/footer/'.$tema.'/iso-pagopar.png" alt="Pagopar"></p>
                    <script type="text/javascript">
        var $ = jQuery;
        $(".pagopar_payments > li > label, .payment_methods > li > input").click(function(){
            var valModopago = jQuery("html").find("input[name=\'modopago\']:checked").val();
            jQuery("input[name=\'billing_metodo_pago\']").val(valModopago);
        });
        $(".payment_methods > li > label, .payment_methods > li > input").click(function(){
            $(".payment_methods > li .payment_box").hide();
            $(this).parent().children(\'.payment_box\').show();
        });
        $(".more_methods").click(function(e){
            var hiddenitems = $(this).parent(\'.methods_group\').children(\'.hidden_method\');
            var cant = hiddenitems.length;
            e.preventDefault();
            hiddenitems.toggle();
            $(this).children(\'.show_more\').toggleClass(\'active\');
            if(hiddenitems.is(\':visible\')) {
                $(this).children(\'.show_more\').text(\'-\' + cant);
            } else {
                $(this).children(\'.show_more\').text(\'+\' + cant);
            }
        });
        $(\'.pagopar_payments > li\').find(\'input[type=radio]:checked\').parents(\'li\').addClass(\'active\');
        $(".pagopar_payments > li > label").click(function(){
            $(this).parents(\'.pagopar_payments\').children(\'li\').removeClass(\'active\');
            $(this).parent().addClass(\'active\');
        });
                </script>');
            return apply_filters( 'woocommerce_gateway_description', $description_html, $this->id );
        }

    public function get_icon()
    {
        global $woocommerce;
        $icon_html = '';
        $urlBasePlugin = plugin_dir_url(dirname(dirname(__FILE__)));

        $tema = 'dark';

        $icon_html .= sprintf('<span class="sub">Utilizá los fondos de tu billetera</span></label><span class="methods_group">
                    <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/tigo-money.png" alt=""></span>
                    <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/billetera-personal.png" alt=""></span>
                    <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/zimple.png" alt=""></span>
                </span>');

        
        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
    }

    function process_payment($order_id)
    {
        $pagopar_gateway = new Pagopar_Gateway();
        $response = $pagopar_gateway->process_payment($order_id);
        return $response;
    }

    

    /**
    * Initialize Gateway Settings Form Fields.
    */
    function init_form_fields() {
        $pagopar_gateway = new Pagopar_Gateway();
        $pagopar_gateway->init_form_fields();
    } //end init_form_fields()

    
    
    /**
     * Get the Mercanet form.
     */
    function receipt($order_id) {
        
    } // end receipt()

    /**
     * Payment API
     */
    public function api() {
        
    }
    
    /**
     * Get params
     */
    private function get_params(){
      
    }
    
} //end WCMPG_Mercanet
