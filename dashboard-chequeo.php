<?php

global $wpdb;

# Traemos info sobre la configuracion del plugin de Pagopar
$payments = WC()->payment_gateways->payment_gateways();

$citiesConsultPagopar = new ConsultPagopar($origin_pagopar);
$citiesConsultPagopar->publicKey = $payments['pagopar']->settings['public_key'];
$citiesConsultPagopar->privateKey = $payments['pagopar']->settings['private_key'];

$cats = $citiesConsultPagopar->getProductCategories('todas');

$cats = json_decode(json_encode($cats), true);

# Agrupamos en un nuevo array donde esten las categorias que no tengan soporte de AEX
foreach ($cats as $key => $value) {
    if (trim($value['envio_aex'])===''){
        $categoriasSinAEX[$value['categoria']] = $cats[$key];
        $categoriasSinAEXIdentificador[] = $value['categoria'];
    }
	$categoriasIdentificadorTodos[$value['categoria']] = $cats[$key];
	
}

$metodoEnvioPagoarHabilitado = metodoEnvioPagoarHabilitado();
# si está habilitado aex, verificamos los productos que no tienen asignado dimensiones/peso, o que la categoría seleccionada no posee AEX
if ($metodoEnvioPagoarHabilitado===true){
    $productosFisicos = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->prefix . "posts p WHERE p.post_type=%s AND p.post_status='publish' and (select pm.meta_value from " . $wpdb->prefix . "postmeta pm where pm.post_id = p.ID and pm.meta_key = '_virtual' and pm.meta_value = 'yes') is null  ", 'product'));
    $contador = 0;
    foreach ($productosFisicos as $key => $value) {
        $productosFisicosDatosAdicionales = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value from " . $wpdb->prefix . "postmeta where meta_key in ('pagopar_final_cat','pagopar_largo','pagopar_ancho','pagopar_alto','pagopar_direccion_id_woocommerce', '_height', '_length', '_width', '_weight') and post_id = %s  ", $value->ID));
        foreach ($productosFisicosDatosAdicionales as $key2 => $value2) {
            if ($value2->meta_key==='pagopar_final_cat'){
                if (in_array($value2->meta_value, $categoriasSinAEXIdentificador)){
                    
                      
                    $productosConProblemas[$contador]['categoria'] = $categoriasSinAEX[$value2->meta_value];
                    $productosConProblemas[$contador]['post_id'] = $value->ID;
                    $productosConProblemas[$contador]['post_title'] = $value->post_title;
                    $productosConProblemas[$contador]['post_guid'] = $value->guid;
                    $productosConProblemas[$contador]['motivo_problema'] = 'La categoría no soporta AEX.<br />';
					
					$urlErrorDocumentacion = 'https://soporte.pagopar.com/portal/es/kb/articles/chequeo-de-configuraci%C3%B3n#La_categora_no_soporta_AEX';
		            $productosConProblemas[$contador]['motivo_problema_url'] = $urlErrorDocumentacion;
       				$productosProblemas[] = $value->ID;


                            
                    $contador = $contador + 1;
                    
                }
				
				/*if ($value2->meta_value=='980'){
                    
                      
                    $productosConProblemas[$contador]['categoria'] = $categoriasIdentificadorTodos[$value2->meta_value];
                    $productosConProblemas[$contador]['post_id'] = $value->ID;
                    $productosConProblemas[$contador]['post_title'] = $value->post_title;
                    $productosConProblemas[$contador]['post_guid'] = $value->guid;
                    $productosConProblemas[$contador]['motivo_problema'] = 'La categoría '.$categoriasIdentificadorTodos[$value2->meta_value].'no soporta AEX';

                            
                    $contador = $contador + 1;
                    
                }*/
				
				/*if ($value->ID==1041){
					var_dump($value);
					var_dump($value2->meta_value);
					var_dump(($value2->meta_value=='980'));
				}*/
				
				
            }
           
            
        }
        
    }
	
	#print_r($productosConProblemas);
	
    
    # Recorremos los postmenta del producto para ver si tiene datos faltantes
    $productosFisicos = $wpdb->get_results($wpdb->prepare("SELECT ID, post_title,
(SELECT meta_value from " . $wpdb->prefix . "postmeta pm where meta_key in ('pagopar_final_cat') and pm.post_id = p.ID ) as pagopar_final_cat,
(SELECT meta_value from " . $wpdb->prefix . "postmeta pm where meta_key in ('pagopar_largo') and pm.post_id = p.ID ) as pagopar_largo,
(SELECT meta_value from " . $wpdb->prefix . "postmeta pm where meta_key in ('pagopar_ancho') and pm.post_id = p.ID ) as pagopar_ancho,
(SELECT meta_value from " . $wpdb->prefix . "postmeta pm where meta_key in ('pagopar_alto') and pm.post_id = p.ID ) as pagopar_alto,
(SELECT meta_value from " . $wpdb->prefix . "postmeta pm where meta_key in ('pagopar_direccion_id_woocommerce') and pm.post_id = p.ID ) as pagopar_direccion_id_woocommerce,
(SELECT meta_value from " . $wpdb->prefix . "postmeta pm where meta_key in ('_height') and pm.post_id = p.ID ) as _height,
(SELECT meta_value from " . $wpdb->prefix . "postmeta pm where meta_key in ('_length') and pm.post_id = p.ID ) as _length,
(SELECT meta_value from " . $wpdb->prefix . "postmeta pm where meta_key in ('_width') and pm.post_id = p.ID ) as _width,
(SELECT meta_value from " . $wpdb->prefix . "postmeta pm where meta_key in ('_weight') and pm.post_id = p.ID ) as _weight
FROM " . $wpdb->prefix . "posts p WHERE p.post_type=%s AND p.post_status='publish' and (select pm.meta_value from " . $wpdb->prefix . "postmeta pm where pm.post_id = p.ID and pm.meta_key = '_virtual' and pm.meta_value = 'yes') is null  ", 'product'));
    //$contador = 0;
    foreach ($productosFisicos as $key => $value) {
        
        $mensajeError = '';
        $urlErrorDocumentacion = '';

        # SI no existe categoria pagopar definida, verificamos que tengan definido valores de alto, largo ancho y peso de Woocommerce
        if (!is_numeric($value->pagopar_final_cat)){
            
            $errorCateogiaAsignada = 'El producto no tiene definido la categoria Pagopar.<br />';

            $sinMedidasWoocommerce = false;
            if (!is_numeric($value->_height)){
                $mensajeError .= 'El producto no tiene definido el alto.<br />';
                $sinMedidasWoocommerce = true;
            }
            if (!is_numeric($value->_length)){
                $mensajeError .= 'El producto no tiene definido el largo.<br />';
                $sinMedidasWoocommerce = true;
            }
            if (!is_numeric($value->_width)){
                $mensajeError .= 'El producto no tiene definido el ancho.<br />';
                $sinMedidasWoocommerce = true;
            }
            if (!is_numeric($value->_weight)){
                $mensajeError .= 'El producto no tiene definido el peso.<br />';
                $urlErrorDocumentacion = 'https://soporte.pagopar.com/portal/es/kb/articles/chequeo-de-configuraci%C3%B3n';
                $sinMedidasWoocommerce = true;
            }
            
            if ($sinMedidasWoocommerce === true){
                $mensajeError = '';
                $mensajeError .= 'El producto no tiene definida la categoria Pagopar ni medidas. Una de las dos debe estar asignada.<br />';
                $urlErrorDocumentacion = 'https://soporte.pagopar.com/portal/es/kb/articles/chequeo-de-configuraci%C3%B3n#El_producto_no_tiene_definida_la_categoria_Pagopar_ni_medidas_Una_de_las_dos_debe_estar_asignada';

            }
            
            
            

        }
        
        $direccionUnicaHabilitada = get_option('direccion_unica_habilitada');
        
        if ($direccionUnicaHabilitada !== '1'){
            if (!is_numeric($value->pagopar_direccion_id_woocommerce)){
                $mensajeError .= 'El producto no tiene definido una dirección.<br />';
				
				$urlErrorDocumentacion = 'https://soporte.pagopar.com/portal/es/kb/articles/chequeo-de-configuraci%C3%B3n#El_producto_no_tiene_definido_una_direccin';
				//$productosConProblemas[$contador]['motivo_problema_url'] = $urlErrorDocumentacion;				
				
            }
        }



        if ($mensajeError!==''){
            $productosConProblemas[$contador]['categoria'] = $categoriasSinAEX[$value2->meta_value];
            $productosConProblemas[$contador]['post_id'] = $value->ID;
            $productosConProblemas[$contador]['post_title'] = $value->post_title;
            $productosConProblemas[$contador]['post_guid'] = $value->guid;
            $productosConProblemas[$contador]['motivo_problema'] = $mensajeError;
            $productosConProblemas[$contador]['motivo_problema_url'] = $urlErrorDocumentacion;
			$productosProblemas[] = $value->ID;
            

            $contador = $contador + 1;

        }

        
        
        
    }    
    
    
    $woocommerce_shipping_debug_mode = get_option('woocommerce_shipping_debug_mode');
    $woocommerce_default_country = get_option('woocommerce_default_country');

    $contador = 0;
    if ($woocommerce_shipping_debug_mode!=='yes'){
        
            $configuracionProblema[$contador]['motivo_problema'] = 'Modo de depuración de envío deshabilitado';
            $configuracionProblema[$contador]['explicacion_problema'] = 'Esta es una advertencia, ya que tener deshabilitada esta opción no necesariamente causará que no funcione bien el cálculo de envío, pero si tiene algún plugin de caché si lo hará. Recomendamos activar esta opción para garantizar un buen funcionamiento.<br />';
            $configuracionProblema[$contador]['motivo_problema_url'] = 'https://soporte.pagopar.com/portal/es/kb/articles/chequeo-de-configuraci%C3%B3n#Modo_de_depuracin_de_envo_deshabilitado';
            $contador = $contador + 1;
    }
    
    # Cuando aex esta habilitado solo pais: PY
    # Cuando aex esta habilitado con ciudad: PY:PY1
    # Cuando aex no esta habilitado: PY:PY-ASU
    $woocommerce_default_country = explode(':', $woocommerce_default_country);
    $woocommerce_default_country_ciudad = $woocommerce_default_country[1];
    $woocommerce_default_country_ciudad = substr($woocommerce_default_country_ciudad, 2);
    if (($woocommerce_default_country[0]!=='PY') or ((!is_numeric($woocommerce_default_country_ciudad)) or ($woocommerce_default_country_ciudad)==='')){
        
            $configuracionProblema[$contador]['motivo_problema'] = 'País/Provincia no perteneciente a Paraguay';
            $configuracionProblema[$contador]['explicacion_problema'] = 'Esta es una advertencia, ya que tener definido el País definido a "Paraguay" no es necesariamente un problema, pero si su tienda es de Paraguay debe setearlo tal cual. Para setear: Woocommerce > Ajustes: Seleccionar Paraguay donde dice Pais/Provincia.<br />';
            #$configuracionProblema[$contador]['motivo_problema_url'] = 'https://soporte.pagopar.com/portal/es/kb/articles/chequeo-de-configuraci%C3%B3n#Modo_de_depuracin_de_envo_deshabilitado';
            $contador = $contador + 1;
    }
        
    
    
    
    $cantidadConfiguracionProblema = $contador;
    
    
 
}


    if (isset($_POST['descachear'])){
        $direccionActual = traerDirecciones($_GET['direccion']);
        $direccionActual = $direccionActual[0];

            // DELETE FROM wp_options WHERE `wp_options`.`option_id` = 475"
        //pagopar_fecha_datos_comercios_actualizacion

        $productosFisicos = $wpdb->get_results($wpdb->prepare("DELETE FROM " . $wpdb->prefix . "options WHERE option_name = %s ", 'pagopar_fecha_datos_comercios_actualizacion'));



    }


?>
<div class="wrap">
    
          <div>
            <h2>Caché de Pagopar:</h2>
	</div>
	<br />  
        <?php if (isset($_POST['descachear'])):?>
        Borrado exitosamente
        <?php endif;?>
            <form method="post" action="">

                <p class="submit">
                <input type="hidden" value="1" name="borrar_cache" />
                <button name="descachear" class="button-primary woocommerce-save-button" type="submit" id="descachear" value="Guardar los cambios">Borrar datos de cache de Pagopar</button>
            </p>

        </form>
    
<h1 class="wp-heading-inline">Chequeo de la configuración del plugin de Pagopar</h1>
    
<div style="clear:both;"></div>



<div class="wrap" identificador="">
    
    
    <?php if ($metodoEnvioPagoarHabilitado===true): ?>

        <div>
            <h2>A continuación algunos problemas de configuración de del sitio:</h2>
	</div>
	<br />

    <table class="wp-list-table widefat fixed striped posts">
      <tr>
        <th><strong>Problema detectado</strong></th>
        <th><strong>Solución detectado</strong></th>
      </tr> 
		
		<?php
		foreach ($configuracionProblema as $key => $value) :
		?>
		<tr>
			<td><?php echo $value['motivo_problema'];?></td>
			<td><?php 
                        
                        echo $value['explicacion_problema'];
                        if (trim($value['motivo_problema_url'])!==''):
                            ?>
                            <a target="_blank" href="<?php echo $value['motivo_problema_url'];?>">Ver cómo solucionar el problema</a>
                            <?php
                        endif;
                        
                        
                        
                        ?></td>
		</tr>		
		<?php                 
                endforeach; ?>
    
    </table>   
	<br /> Cantidad de problemas encontrados: <?php echo $cantidadConfiguracionProblema;?>
        <hr />
	
    <?php endif;?>
    

    
	<div>
            <h2>A continuación algunos problemas de configuración de productos detectados:</h2>
	</div>
	<br />

    <table class="wp-list-table widefat fixed striped posts">
      <tr>
        <th><strong>ID</strong></th>
        <th><strong>Titulo</strong></th>
        <th><strong>Categoria</strong></th>
        <th><strong>Problema detectado</strong></th>
      </tr> 
		
		<?php
		foreach ($productosConProblemas as $key => $value) :
		if (!in_array($value['categoria']['categoria'], array(909,5))):
		?>
		<tr>
			<td><?php echo $value['post_id'];?></td>
                        <td><?php echo '<a target="_blank" href="'.get_edit_post_link($value['post_id']).'">'.$value['post_title'].'</a>';?></td>
			<td><?php echo $value['categoria']['descripcion'].' - '.$value['categoria']['categoria'];?></td>
			<td><?php 
                        
                        echo $value['motivo_problema'];
                        if (trim($value['motivo_problema_url'])!==''):
                            ?>
                            <a target="_blank" href="<?php echo $value['motivo_problema_url'];?>">Ver cómo solucionar el problema</a>
                            <?php
                        endif;
                        
                        
                        
                        ?></td>
		</tr>		
		<?php                 
                endif;
                endforeach; ?>
    
    </table>
    <?php if(count((array)$productosConProblemas)>0):?>
        <br /> Cantidad de problemas encontrados: <?php echo count((array)$productosConProblemas);?>
    <?php else:?>
        <br /> Cantidad de problemas encontrados: 0
    <?php endif;?>

    <?php if(count((array)$productosProblemas)>0):?>
        <br /> Cantidad de productos con problemas encontrados: <?php echo count((array)array_unique($productosProblemas));?>
    <?php else:?>
        <br /> Cantidad de productos con problemas encontrados: 0
    <?php endif;?>

	


</div>
                
<br />
<br />

</div>