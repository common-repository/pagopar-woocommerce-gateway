<?php

global $wpdb;

# Actualizamos valores del form
if (isset($_POST['actualizar'])){
    
    update_option('pagopar_footer_tema_base', $_POST['pagopar_footer_tema_base']);
    update_option('pagopar_color_fondo', $_POST['pagopar_color_fondo']);
    update_option('pagopar_color_borde_superior', $_POST['pagopar_color_borde_superior']);
    update_option('pagopar_ocultar_footer', $_POST['pagopar_ocultar_footer']);
    
}

# Obtenemos valores guardados
$pagopar_footer_tema_base = get_option('pagopar_footer_tema_base');
$pagopar_color_fondo = get_option('pagopar_color_fondo');
$pagopar_color_borde_superior = get_option('pagopar_color_borde_superior');
$pagopar_ocultar_footer = get_option('pagopar_ocultar_footer');

# Seteamos valores por defecto
if ($pagopar_footer_tema_base==''){
    $pagopar_footer_tema_base = 'dark';
}

if ($pagopar_color_fondo==''){
    $pagopar_color_fondo = '#333333';
}

if ($pagopar_color_borde_superior==''){
    $pagopar_color_borde_superior = '#333333';
}



# Traemos info sobre la configuracion del plugin de Pagopar
$payments = WC()->payment_gateways->payment_gateways();

$citiesConsultPagopar = new ConsultPagopar($origin_pagopar);
$citiesConsultPagopar->publicKey = $payments['pagopar']->settings['public_key'];
$citiesConsultPagopar->privateKey = $payments['pagopar']->settings['private_key'];

$formasPago = traer_medios_pago_disponibles($citiesConsultPagopar->publicKey, $citiesConsultPagopar->privateKey);

update_option('pagopar_formas_pago', $formasPago);


?>
<div class="wrap">
    
    
<h1 class="wp-heading-inline">Footer de Pagopar</h1>
    
<div style="clear:both;"></div>
<div class='update-nag'>Mostrar nuestro footer nos ayuda a mejorar constantemente nuestro plugin.</div>
<div style="clear:both;"></div>
<div>
    <form method="post" action="">
        
        
        
<table class="form-table">

    <tbody>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="woocommerce_pagopar_seller_phone">Tema base </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Tema base </span></legend>

                    <select class="select wc-enhanced-select" name="pagopar_footer_tema_base" id="pagopar_footer_tema_base" style="" tabindex="-1" aria-hidden="true">
                        <option value="dark" <?php if ($pagopar_footer_tema_base==='dark'): echo ' selected="selected" '; endif;?> >Dark</option>
                        <option value="light" <?php if ($pagopar_footer_tema_base==='light'): echo ' selected="selected" '; endif;?>> Light</option>
                    </select>

                </fieldset>
            </td>
        </tr>

        
        <tr valign="top">
            <th scope="row" class="titledesc">
                        <label for="woocommerce_pagopar_seller_phone">Color de fondo</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Color de fondo</span></legend>

                    <input type="text"  name="pagopar_color_fondo" class="mi-plugin-color-field" data-default-color="<?php echo $pagopar_color_fondo;?>" value="<?php echo $pagopar_color_fondo;?>" />

                </fieldset>
            </td>
        </tr>                
            
        
        <tr valign="top">
            <th scope="row" class="titledesc">
                        <label for="woocommerce_pagopar_seller_phone">Color de borde superior</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Color de borde superior</span></legend>

                    <input type="text" name="pagopar_color_borde_superior" class="mi-plugin-color-field" data-default-color="<?php echo $pagopar_color_borde_superior;?>" value="<?php echo $pagopar_color_borde_superior;?>" />

                </fieldset>
            </td>
        </tr>        
        
        
        <tr valign="top">
                <th scope="row" class="titledesc">
                        <label for="woocommerce_pagopar_mostrar_siempre_todos_medios_pago">Ocultar footer</label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Ocultar footer</span></legend>
                        <label for="pagopar_ocultar_footer">
                            <input  class="" type="checkbox" name="pagopar_ocultar_footer" id="pagopar_ocultar_footer" style="" value="1" <?php if ($pagopar_ocultar_footer=='1'): echo ' checked="checked" '; endif;?> /> Ocultar footer</label><br/>
                    </fieldset>
                </td>
        </tr>        
        

</table>
        
        
        
<p class="submit">
    <button name="actualizar" class="button-primary woocommerce-save-button" type="submit" id="split_billing_actualizar" value="Guardar los cambios">Actualizar</button>
</p>
        
    </form>    
    
    
    
</div>


</div>

<script>


jQuery(document).ready(function($){
	var opciones = {
	    // Podemos declarar un color por defecto aquí
	    // o en el atributo del input data-default-color
	    defaultColor: false,
	    // llamada que se lanzará cuando el input tenga un color válido
	    change: function(event, ui){},
	    // llamada que se lanzará cuando el input tenga un color no válido
	    clear: function() {},
	    // esconde los controles del Color Picker al cargar
	    hide: true,
	    // muestra un grupo de colores comunes debajo del selector
	    // o suministra de una gama de colores para poder personalizar más aun.
	    palettes: true
	};
    $('.mi-plugin-color-field').wpColorPicker(opciones);
});


</script>