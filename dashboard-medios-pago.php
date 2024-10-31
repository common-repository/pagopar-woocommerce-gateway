<div class="wrap">
    
    
<h1 class="wp-heading-inline">Medios de Pagos</h1>


<?php 

global $wpdb;

# Traemos info sobre la configuracion del plugin de Pagopar
$payments = WC()->payment_gateways->payment_gateways();

$citiesConsultPagopar = new ConsultPagopar($origin_pagopar);
$citiesConsultPagopar->publicKey = $payments['pagopar']->settings['public_key'];
$citiesConsultPagopar->privateKey = $payments['pagopar']->settings['private_key'];


#include 'pagopar-functions.php';
#include 'pagopar-functions-api.php';


$formasPago = traer_medios_pago_disponibles($citiesConsultPagopar->publicKey, $citiesConsultPagopar->privateKey);

$datosComercio = traerDatosComercio();
$datosComercio = json_decode($datosComercio, true);

?>

<?php
if ($formasPago['respuesta']===false):
?>
<div style="clear:both;"></div>
<div class='update-nag'>Error al listar los medios disponibles ofrecidos por Pagopar, esto muy seguramente se deba a una mala configuración del plugin. <a target="_blank" href="<?php echo get_admin_url();?>admin.php?page=wc-settings&tab=checkout&section=pagopar">Favor configure el plugin correctamente</a>.</div>
<div style="clear:both;"></div>
<?php
endif;
?>




<div class="wrap" identificador="zona_<?php echo $value2['instance_id'];?>">
	<div>
		Dependiendo de la configuración de su cuenta, tiene a disposición ciertos medios de pago ofrecidos por Pagopar, que se listan a continuación.
	</div>
	<br />

    <table class="wp-list-table widefat fixed striped posts">
      <tr>
        <th><strong>Titulo</strong></th>
        <th><strong>Descripción</strong></th>
        <th><strong>Monto mínimo permitido</strong></th>
      </tr> 
		
		<?php
		foreach ($formasPago['resultado'] as $key => $value) :
		?>
		<tr>
			<td><?php echo $value['titulo'];?></td>
			<td><?php echo $value['descripcion'];?></td>
			<td><?php echo $value['monto_minimo'];?></td>
		</tr>		
		<?php endforeach; ?>
    
    </table>   


</div>
                
<br />
<br />
<div class="wrap" identificador="zona_<?php echo $value2['instance_id'];?>">
	<div>
            <strong>Configuración del comercio establecida en Pagopar.com</strong>
	</div>
    
    <br />

    <table class="wp-list-table widefat fixed striped posts">
      <tr>
        <th><strong>Nombre</strong></th>
        <th><strong>Valor</strong></th>
      </tr> 
		
		<tr>
                    <td>Catastro de tarjetas de crédito/débito</td>
                    <td><?php if ($datosComercio['resultado']['contrato_firmado']===true): ?>Si<?php else:?>No<?php endif;?></td>
		</tr>		
		
		<tr>
                    <td>¿Cómo cobro mis ventas?</td>
                    <td><?php echo $datosComercio['resultado']['modo_pago_denominacion']; ?></td>
		</tr>	                
		
		<tr>
                    <td>Sincronización habilitada</td>
                    <td><?php if ($datosComercio['resultado']['permisos_link_venta_comercio']===true): ?>Si<?php else:?>No<?php endif;?></td>
		</tr>	
		
		<tr>
                    <td>Entorno</td>
                    <td><?php echo $datosComercio['resultado']['entorno']; ?></td>
		</tr>                
    
    </table> 
    
    
    
</div>


</div>