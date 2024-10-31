<?php



class Pagopar_Tarjetas extends WC_Payment_Gateway {

    
    /**
    * Constructor
    */
    function __construct() {
        // Recovers the necessary functions
        

        $this->id = 'pagopar_tarjetas';
        $this->icon = '"width=50px"';

        $this->has_fields = false;
        $this->method_title = 'Pagopar';
        $this->title = "Tarjetas de Crédito";
        $this->method_description = __( 'Se aceptan tarjetas nacionales e internacionales', 'pagopar_tarjetas' );
        // Load the form fields
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();

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
            $description_html .= sprintf('
                    <ul class="pagopar_payments">
            <li>
              <label for="sub_payment_method2">
                <input id="sub_payment_method2" type="radio" class="input-radio" name="modopago" value="9" data-order_button_text="">
                Tarjetas nacionales con Bancard
              </label>
              <span class="methods_group">
                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/mastercard.png" alt=""></span>
                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/aex.png" alt=""></span>
                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/visa.png" alt=""></span>
                <!-- lista de ocultos -->
                <span class="method_item hidden_method"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/cabal.png" alt=""></span>
                <span class="method_item hidden_method"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/panal.png" alt=""></span>
                <span class="method_item hidden_method"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/credicard.png" alt=""></span>
                <span class="method_item hidden_method"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/credifielco.png" alt=""></span>
                <!-- boton mostrar mas -->
                <span class="method_item more_methods"><span class="show_more">+4</span></span>
              </span>
            </li>
            <li>
              <label for="sub_payment_method">
                <input id="sub_payment_method" type="radio" class="input-radio" name="modopago" value="1" data-order_button_text="">
                Tarjetas internacionales con Procard (Visa y MC emitidas en el exterior)
              </label>
              <span class="methods_group">
                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/mastercard.png" alt=""></span>
                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/visa.png" alt=""></span>
                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/credicard.png" alt=""></span>
                <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/unica.png" alt=""></span>
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

        //$urlBasePlugin . 'images/footer/' . $tema . '/visa.png

        $icon_html .= sprintf('<span class="sub">Se aceptan tarjetas nacionales e internacionales</span></label><span class="methods_group">
                    <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/visa.png" alt=""></span>
                    <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/mastercard.png" alt=""></span>
                    <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/aex.png" alt=""></span>
                    <span class="method_item"><img src="'.$urlBasePlugin.'images/footer/'.$tema.'/cabal.png" alt=""></span>
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
