<?php



class Pagopar_Efectivo extends WC_Payment_Gateway {

    /**
    * Constructor
    */
    function __construct() {
        // Recovers the necessary functions
        
     // Load the form fields
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();
        $this->id = 'pagopar_efectivo';
        $this->icon = '"width=50px"';
        
        $this->has_fields = false;
        $this->method_title = 'Pagopar';
        
        $this->title = "Efectivo";
        $this->method_description = __( 'Acercándose a las bocas de pagos habilitadas', 'pagopar_efectivo' );
        // Load the form fields
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();

        // Get setting values
        

    

        // Hooks
       
    } //end __construct()

    /**
    * Admin tools
    */
     public function get_description() {
            $urlBasePlugin = plugin_dir_url(dirname(dirname(__FILE__)));

            $tema = 'dark';
            $description_html = '';
            $description_html .= sprintf('
                    <ul class="pagopar_payments">
                        <li>
                            <label for="sub_payment_method6">
                                <input id="sub_payment_method6" type="radio" class="input-radio" name="modopago" value="2" data-order_button_text="">
                                Aqui Pago
                            </label>
                            <span class="methods_group">
                                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/aquipago.png" alt=""></span>
                            </span>
                        </li>
                        <li>
                            <label for="sub_payment_method7">
                                <input id="sub_payment_method7" type="radio" class="input-radio" name="modopago" value="3" data-order_button_text="">
                                Pago Express
                            </label>
                            <span class="methods_group">
                                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/pagoexpress.png" alt=""></span>
                            </span>
                        </li>
                        <li>
                            <label for="sub_payment_method8">
                                <input id="sub_payment_method8" type="radio" class="input-radio" name="modopago" value="4" data-order_button_text="">
                                Practipago
                            </label>
                            <span class="methods_group">
                                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/practipago.png" alt=""></span>
                            </span>
                        </li>
                    </ul>
                    <p class="pagopar-copy">Procesado por Pagopar <img src="'.$urlBasePlugin.'images/footer/'.$tema.'/iso-pagopar.png" alt="Pagopar"></p>
                <script> 
                $(".pagopar_payments > li > label, .payment_methods > li > input").click(function(){
                    var valModopago = jQuery("html").find("input[name=\'modopago\']:checked").val();
                    jQuery("input[name=\'billing_metodo_pago\']").val(valModopago);
                });
                </script>
                ');
            return apply_filters( 'woocommerce_gateway_description', $description_html, $this->id );
        }

    public function get_icon()
    {
        global $woocommerce;
        $icon_html = '';

        $urlBasePlugin = plugin_dir_url(dirname(dirname(__FILE__)));

        $tema = 'dark';

        $icon_html .= sprintf('<span class="sub">Acercándose a las bocas de pagos habilitadas</span></label><span class="methods_group">
                    <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/aquipago.png" alt=""></span>
                    <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/pagoexpress.png" alt=""></span>
                    <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/practipago.png" alt=""></span>
                </span>');
        return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
    }

    /**
    * Initialize Gateway Settings Form Fields.
    */
    function init_form_fields() {
        
    } //end init_form_fields()

    function process_payment($order_id)
    {
        $pagopar_gateway = new Pagopar_Gateway();
        $response = $pagopar_gateway->process_payment($order_id);
        return $response;
    }
    
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
