<div class="wrap">
<h1 class="wp-heading-inline">Split Billing</h1>


<?php 

global $wpdb;

# Traemos info sobre la configuracion del plugin de Pagopar
$payments = WC()->payment_gateways->payment_gateways();

$citiesConsultPagopar = new ConsultPagopar($origin_pagopar);
$citiesConsultPagopar->publicKey = $payments['pagopar']->settings['public_key'];
$citiesConsultPagopar->privateKey = $payments['pagopar']->settings['private_key'];


$comerciosHijosJsonGuardado = $payments['pagopar']->settings['json_comercio_hijos'];
$comerciosHijosJsonGuardado = json_decode($comerciosHijosJsonGuardado, true);

#ini_set('display_errors', 'on');
#error_reporting(E_ALL);


#include 'pagopar-functions.php';
#include 'pagopar-functions-api.php';
#var_dump($comerciosHijosJsonGuardado);die();

/*temp
if (count($comerciosHijosJsonGuardado['resultado'])>0){
    $comerciosHijos = $comerciosHijosJsonGuardado;
}else{
    $comerciosHijos = traer_comercios_hijos_asociados($citiesConsultPagopar->publicKey, $citiesConsultPagopar->privateKey);    

}
*/
$comerciosHijos = traer_comercios_hijos_asociados($citiesConsultPagopar->publicKey, $citiesConsultPagopar->privateKey);    



$cantidadComerciosHijos = intval(count($comerciosHijos['resultado']));

?>

<?php
if ($payments['pagopar']->settings['habilitar_split_billing']!=='yes'):
?>
<div style="clear:both;"></div>
<div class='update-nag'>La opción de split billing está deshabilitada. <a target="_blank" href="<?php echo get_admin_url();?>admin.php?page=wc-settings&tab=checkout&section=pagopar">Favor habilite la opción en el apartado Split Billing</a>.<br />
Favor tenga en cuenta que esta función está limitada por su tipo de Plan en Pagopar, y además está implementada con compatibilidad de sólo algunas funciones de Woocommerce. Para más información puede contactar con Soporte de Pagopar.
</div>
<div style="clear:both;"></div>
<?php
return;
endif;
?>





<div style="clear:both;"></div>
<div class='update-nag'>Por cada venta tu comercio ganará <?php echo floatval($payments['pagopar']->settings['porcentaje_comision_comercio_padre']);?>%. Tenés <?php echo $cantidadComerciosHijos; ?> comercios asociados.
<!--
<p class="submit">
    <button name="split_billing_actualizar" class="button-primary woocommerce-save-button" type="submit" id="split_billing_actualizar" value="Guardar los cambios">Actualizar comercios asociados</button>
</p>
-->

</div>
<div style="clear:both;"></div>


<?php
if ($comerciosHijos['respuesta']===false):
?>
<div style="clear:both;"></div>
<div class='update-nag'>Error al listar los medios disponibles ofrecidos por Pagopar, esto muy seguramente se deba a una mala configuración del plugin. <a target="_blank" href="<?php echo get_admin_url();?>admin.php?page=wc-settings&tab=checkout&section=pagopar">Favor configure el plugin correctamente</a>.</div>
<div style="clear:both;"></div>
<?php
endif;
?>

<div class="wrap" identificador="zona_<?php echo $value2['instance_id'];?>">
	<div>
		A continuación la lista de comercios vinculados a su comercio.
	</div>
	<br />

    <table class="wp-list-table widefat fixed striped posts">
      <tr>
        <th><strong>Nombre del Comercio</strong></th>
        <th><strong>Productos asociados</strong></th>
      </tr> 
		
		<?php
                global $wpdb;
                /* Traemos la cantidad de publicaciones asociadas a cada comercio hijo */
                $productosDB = $wpdb->get_results($wpdb->prepare("select meta_value, sum(1) as cantidad from ".$wpdb->prefix."postmeta where meta_key = 'comercio_hijo_vendedor_producto' group by meta_value", $json_pagopar['resultado'][0]['hash_pedido']));
                $productosDB = json_encode($productosDB);
                $productosDB = json_decode($productosDB, true);


                foreach ($comerciosHijos['resultado'] as $key => $value) :
                    
		?>
		<tr>
			<td><?php echo $value['descripcion'];?></td>
                        <td>
                            <?php 
                            
                            foreach ($productosDB as $key2 => $valueA) {
                                if ($valueA['meta_value']===$value['token_publico']){
                                    echo intval($valueA['cantidad']);
                                }
                            }

                            ?>
                            
                            
                        </td>
		</tr>		
		<?php endforeach; ?>
    
    </table>   


</div>
                
<br />
<br />

</div>
<?php

?>
<script>

    jQuery(function(){
      var $ = jQuery;
        
        $( "#split_billing_actualizar" ).on( "click", function() {
            window.location = "<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=pagopar' ); ?>&actualizar-comercios-split-billing"
      });
        
    });
    
   

</script>