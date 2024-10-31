<?php

global $wpdb;
$urlAdmin = $_SERVER['REQUEST_URI'];

if (isset($_GET['editar'])){

    $direcciones = traerDirecciones($_GET['direccion']);

    if (isset($_POST['actualizar'])){
        $direccionActual = traerDirecciones($_GET['direccion']);
        $direccionActual = $direccionActual[0];

        # SI hubo cambios, actualizamos la direccion y ponemos todas los productos para sincronizar nuevamente
        if ((trim($direccionActual->direccion) !== $_POST['direccion']) or ($direccionActual->ciudad !== $_POST['ciudad']) or (trim($direccionActual->direccion_referencia) !== $_POST['direccion_referencia']) or (trim($direccionActual->direccion_coordenadas) !== $_POST['direccion_coordenadas']) or (trim($direccionActual->telefono) !== $_POST['direccion_telefono'])){
            direccionUpdate($_GET['direccion'], $_POST['direccion'], $_POST['ciudad'], $_POST['direccion_referencia'], $_POST['direccion_coordenadas'], $_POST['direccion_telefono'] );
            ponerVolverEnviarDireccionModificada($_GET['direccion']);
        }

        $direcciones = traerDirecciones($_GET['direccion']);


    }


}elseif (isset($_GET['agregar'])){


    if (isset($_POST['actualizar'])){
        # Se agrega nueva direccion
        crearEditatDireccion($_POST['direccion'], $_POST['ciudad'], $_POST['direccion_referencia'], $_POST['direccion_coordenadas'], $_POST['direccion_telefono']);
        header('Location: '.str_replace('&agregar', '', $urlAdmin));
        exit();
    }


}else{


    # Actualizamos valores del form
    if (isset($_POST['actualizar'])){


        # Ponemos como volver a enviar Pagopar (Sincronizacion) los productos que no usaban la nueva direccion global
        if ($_POST['direccion_unica_habilitada']==='1'){
            ponerVolverEnviarDireccionGlobalModificada($_POST['pagopar_direccion_id']);
        }



        update_option('pagopar_envio_aex_pickup_horario_inicio', $_POST['pagopar_envio_aex_pickup_horario_inicio']);
        update_option('pagopar_envio_aex_pickup_horario_fin', $_POST['pagopar_envio_aex_pickup_horario_fin']);
        # faltaria actualizar los productos para que se vuelva enviar (como ponerVolverEnviarDireccionGlobalModificada()), para que se sincronicen, de datos de aex y de mobi


        update_option('direccion_unica_habilitada', $_POST['direccion_unica_habilitada']);

        # AEX
        update_option('pagopar_aex_activo_general', $_POST['pagopar_aex_activo_general']);
        update_option('pagopar_aex_comentario_pickup', $_POST['pagopar_aex_comentario_pickup']);

        # Mobi
        //echo "ENVIOS MOBI: ".$_POST['pagopar_mobi_activo_general'];die();
        update_option('pagopar_mobi_activo_general', $_POST['pagopar_mobi_activo_general']);

        /******CAPTURAR LOS DIAS/HORAS PARA MOBI*****/
        update_option('pagopar_mobi_hora_contador', $_POST['pagopar_mobi_hora_contador']);
        $dias = [];
        $array_hora = array();
        $hora_mobi_inicio = null;
        $hora_mobi_fin = null;
        for($var_vertical=1;$var_vertical<11;$var_vertical++){
            $dias = [];
             for($var_horizontal=1;$var_horizontal<11;$var_horizontal++){
                 $hora_mobi_inicio = $_POST['hora_mobi_inicio_'.$var_vertical];
                 $hora_mobi_fin = $_POST['hora_mobi_fin_'.$var_vertical];
                if(!empty($_POST['dia-'.$var_horizontal.'-'.$var_vertical])){
                    array_push($dias, $_POST['dia-'.$var_horizontal.'-'.$var_vertical]);
                }
            }
            $dias = implode(", ",$dias);
             if(empty($dias)){
                 $dias = 0;
             }
            if(!empty($_POST['hora_mobi_inicio_'.$var_vertical])){
                $head = array('hora_inicio'=>$hora_mobi_inicio,
                    'hora_fin'=>$hora_mobi_fin,
                    'dias'=>$dias);
                array_push($array_hora,$head);
            }
        }

        $pagopar_mobi_hora = get_option('pagopar_mobi_hora');
        $array_hora = json_encode($array_hora);
        update_option('pagopar_mobi_hora', $array_hora);
        if(empty($pagopar_mobi_hora)){
            add_option('pagopar_mobi_hora', $array_hora);
        }else{
            update_option('pagopar_mobi_hora', $array_hora);
        }
        # Envio propio
        //echo $_POST['pagopar_envio_propio_tiempo_entrega'];die();
        update_option('pagopar_envio_propio_tiempo_entrega', $_POST['pagopar_envio_propio_tiempo_entrega']);
        
        direccionDefectoUpdate($_POST['pagopar_direccion_id']);
    }





    # Obtenemos valores guardados
    $direccion_unica_habilitada = get_option('direccion_unica_habilitada');
    $pagopar_aex_activo_general = get_option('pagopar_aex_activo_general');
    $pagopar_mobi_activo_general = get_option('pagopar_mobi_activo_general');
    $pagopar_aex_comentario_pickup = get_option('pagopar_aex_comentario_pickup');
    $pagopar_envio_propio_tiempo_entrega = get_option('pagopar_envio_propio_tiempo_entrega');





    $direcciones = traerDirecciones();
    $direccionDefecto = traerDireccionDefecto();

    $horarios = array('08:00:00'=>'08:00', '09:00:00'=>'09:00', '10:00:00'=>'10:00', '11:00:00'=>'11:00', '12:00:00'=>'12:00', '13:00:00'=>'13:00', '14:00:00'=>'14:00', '15:00:00'=>'15:00', '16:00:00'=>'16:00', '17:00:00'=>'17:00', '18:00:00'=>'18:00');



}



# Traemos info sobre la configuracion del plugin de Pagopar
$payments = WC()->payment_gateways->payment_gateways();

$citiesConsultPagopar = new ConsultPagopar($origin_pagopar);
$citiesConsultPagopar->publicKey = $payments['pagopar']->settings['public_key'];
$citiesConsultPagopar->privateKey = $payments['pagopar']->settings['private_key'];


######################
$expiroCacheCiudades =  pagoparCacheCurl('pagopar_ciudades_json', 'pagopar_ciudades_fecha');

if ($expiroCacheCiudades===false){
    $cities = json_decode(get_option('pagopar_ciudades_json'));
}else{

    # Se hace la petición a Pagopar
    $cities = $citiesConsultPagopar->getCities();

    # Guardamos solo si el JSON no contenga un error (como error de token)
    if ($cities->respuesta===true){
        update_option('pagopar_ciudades_json', json_encode($cities));
        update_option('pagopar_ciudades_fecha', @date('Y-m-d H:i:s'));
    }
}


$cities_wc_format = array();
if ($cities->respuesta)
{
    foreach ($cities->resultado as $city)
    {
        $cities_wc_format[$city
            ->ciudad] = $city->descripcion;
    }
}
else
{
    $cities_wc_format = $payments['pagopar']->settings['seller_ciudad'];
}



##########################




?>
<script>
    function editarDireccion(){
        var direccion_id = jQuery("#pagopar_direccion_id").val();
        /*alert(jQuery("#pagopar_direccion_id").val());*/
        window.location = "<?php echo $urlAdmin.'&editar&direccion='?>"  + direccion_id;

    }
</script>
<style>
    #dashboardEnvios .form-table th {
        width: 300px !important;
        padding:5px !important;
    }

    #dashboardEnvios .form-table td {
        margin-bottom: 9px;
        padding: 5px 15px 5px 10px;
        line-height: 1.3;
        vertical-align: middle;
    }

    pagoparFloatLeft {float:left;padding:0px 5px 0px 5px;}

</style>
<div class="wrap" id="dashboardEnvios">


    <h1 class="wp-heading-inline">Opciones de Envíos</h1>
    <h2>Direcciones</h2>


    <div>
        <form method="post" action="">


            <?php if (isset($_GET['editar'])):?>

                <table class="form-table">

                    <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_direccion_id">Dirección (calle y número) </label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Dirección (calle y número) </span></legend>

                                <label for="direccion">
                                    <input  class="" type="text" name="direccion" id="direccion" style="" value="<?php echo $direcciones[0]->direccion;?>" />
                                </label><br/>

                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_direccion_id">Referencia de la dirección </label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Referencia de la dirección </span></legend>

                                <label for="direccion_referencia">
                                    <input  class="" type="text" name="direccion_referencia" id="direccion_referencia" style="" value="<?php echo $direcciones[0]->direccion_referencia;?>" />
                                </label><br/>

                            </fieldset>
                        </td>
                    </tr>


                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_telefono_id">Teléfono de contacto</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Teléfono de contacto</span></legend>

                                <label for="direccion_telefono">
                                    <input  class="" type="text" name="direccion_telefono" id="direccion_telefono" style="" value="<?php echo $direcciones[0]->telefono;?>" />
                                </label><br/>

                            </fieldset>
                        </td>
                    </tr>


                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="ciudad">Ciudad</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Ciudad </span></legend>

                                <select class="select wc-enhanced-select" name="ciudad" id="ciudad" style="" tabindex="-1" aria-hidden="true">
                                    <?php foreach ($cities_wc_format as $key => $value) : ?>
                                        <option value="<?php echo $key; ?>" <?php if ($key == $direcciones[0]->ciudad): echo ' selected="selected" '; endif;?> ><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </fieldset>
                        </td>
                    </tr>




                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_direccion_id">Coordenadas</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Coordenadas</span></legend>

                                <label for="direccion_coordenadas">
                                    <input  class="" type="text" name="direccion_coordenadas" id="direccion_coordenadas" style="" value="<?php echo $direcciones[0]->direccion_coordenadas;?>" />
                                </label><br/>

                            </fieldset>
                        </td>
                    </tr>





                </table>
            <?php elseif (isset($_GET['agregar'])):?>


                <table class="form-table" id="tblDoc">

                    <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_direccion_id">Dirección (calle y número) </label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Dirección (calle y número) </span></legend>

                                <label for="direccion">
                                    <input  class="" type="text" name="direccion" id="direccion" style="" value="<?php echo $direcciones[0]->direccion;?>" />
                                </label><br/>

                            </fieldset>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_direccion_id">Referencia de la dirección </label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Referencia de la dirección </span></legend>

                                <label for="direccion_referencia">
                                    <input  class="" type="text" name="direccion_referencia" id="direccion_referencia" style="" value="<?php echo $direcciones[0]->direccion_referencia;?>" />
                                </label><br/>

                            </fieldset>
                        </td>
                    </tr>


                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_telefono_id">Teléfono de contacto</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Teléfono de contacto</span></legend>

                                <label for="direccion_telefono">
                                    <input  class="" type="text" name="direccion_telefono" id="direccion_telefono" style="" value="<?php echo $direcciones[0]->telefono;?>" />
                                </label><br/>

                            </fieldset>
                        </td>
                    </tr>


                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="ciudad">Ciudad</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Ciudad </span></legend>

                                <select class="select wc-enhanced-select" name="ciudad" id="ciudad" style="" tabindex="-1" aria-hidden="true">
                                    <?php foreach ($cities_wc_format as $key => $value) : ?>
                                        <option value="<?php echo $key; ?>" <?php if ($key == $direcciones[0]->ciudad): echo ' selected="selected" '; endif;?> ><?php echo $value; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </fieldset>
                        </td>
                    </tr>




                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_direccion_id">Coordenadas</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Coordenadas</span></legend>

                                <label for="direccion_coordenadas">
                                    <input  class="" type="text" name="direccion_coordenadas" id="direccion_coordenadas" style="" value="<?php echo $direcciones[0]->direccion_coordenadas;?>" />
                                </label><br/>

                            </fieldset>
                        </td>
                    </tr>




                </table>

            <?php else:?>
                <table class="form-table" id="tblDoc">

                    <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_direccion_id">Dirección por defecto </label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Dirección por defecto </span></legend>

                                <select class="select wc-enhanced-select" name="pagopar_direccion_id" id="pagopar_direccion_id" style="" tabindex="-1" aria-hidden="true">
                                    <?php foreach ($direcciones as $key => $value) : ?>
                                        <option value="<?php echo $value->id; ?>" <?php if ($direccionDefecto->id === $value->id): echo ' selected="selected" '; endif;?> ><?php echo $value->direccion; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="javascript:editarDireccion();">Editar esta dirección</a> &nbsp; | &nbsp; <a href="<?php echo $urlAdmin?>&agregar">Agregar nueva dirección</a>
                            </fieldset>
                        </td>
                    </tr>






                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="woocommerce_pagopar_mostrar_siempre_todos_medios_pago">Utilizar esta dirección para todos los productos</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Utilizar esta dirección para todos los productos</span></legend>
                                <label for="direccion_unica_habilitada">
                                    <input  class="" type="checkbox" name="direccion_unica_habilitada" id="direccion_unica_habilitada" style="" value="1" <?php if ($direccion_unica_habilitada=='1'): echo ' checked="checked" '; endif;?> /> Aplicar esta dirección a todos los productos, ya que todos los productos están en la misma dirección</label><br/>
                            </fieldset>
                        </td>
                    </tr>



                    <tr>
                        <th colspan="2"><h2>Opciones del courier AEX</h2></th>
                    </tr>


                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_aex_activo_general">Hablitar AEX</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Hablitar AEX</span></legend>
                                <label for="pagopar_aex_activo_general">
                                    <input  class="" type="checkbox" name="pagopar_aex_activo_general" id="pagopar_aex_activo_general" style="" value="1" <?php if ($pagopar_aex_activo_general=='1'): echo ' checked="checked" '; endif;?> /> Habilitar servicio del courier AEX (el cliente pagará el costo del envío)</label><br/>
                            </fieldset>
                        </td>
                    </tr>






                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_aex_activo_general">Comentarios Pickup</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Comentarios Pickup</span></legend>
                                <label for="pagopar_aex_comentario_pickup">
                                    <input  class="" type="text" name="pagopar_aex_comentario_pickup" id="pagopar_aex_comentario_pickup" style="" value="<?php echo $pagopar_aex_comentario_pickup?>" /></label><br/>
                            </fieldset>
                        </td>
                    </tr>



                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_aex_horarios">Horarios para el retiro del producto</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Horarios para el retiro del producto</span></legend>

                                <?php


                                if (trim($pagopar_envio_aex_pickup_horario_inicio)===''){
                                    $pagopar_envio_aex_pickup_horario_inicio = '08:00:00';
                                }
                                woocommerce_wp_select(array(
                                    'id' => 'pagopar_envio_aex_pickup_horario_inicio',
                                    'label' => 'Desde las ',
                                    'class' => 'wc-enhanced-select pagoparFloatLeft',
                                    'value' => $pagopar_envio_aex_pickup_horario_inicio,
                                    'options' => $horarios,
                                ));

                                if (trim($pagopar_envio_aex_pickup_horario_fin)===''){
                                    $pagopar_envio_aex_pickup_horario_fin = '18:00:00';
                                }
                                woocommerce_wp_select(array(
                                    'id' => 'pagopar_envio_aex_pickup_horario_fin',
                                    'label' => 'Hasta las ',
                                    'class' => 'wc-enhanced-select',
                                    'value' => $pagopar_envio_aex_pickup_horario_fin,
                                    'options' => $horarios,
                                ));


                                ?>

                                <br/>
                            </fieldset>
                        </td>
                    </tr>





                    <?php if (false): ?>
                        <tr>
                            <th colspan="2"><h2>Opciones del courier Mobi</h2></th>
                        </tr>
                        <?php $pagopar_mobi_hora_contador = get_option('pagopar_mobi_hora_contador');?>
                        <tr valign="top">
                            <th scope="row" class="titledesc">
                                <label for="pagopar_mobi_activo_general">Hablitar Mobi</label>
                            </th>
                            <td class="forminp">
                                <fieldset>
                                    <legend class="screen-reader-text"><span>Hablitar Mobi</span></legend>
                                    <label for="pagopar_mobi_activo_general">
                                        <input  class="" type="checkbox" name="pagopar_mobi_activo_general" id="pagopar_mobi_activo_general" style="" value="1" <?php if ($pagopar_mobi_activo_general=='1'): echo ' checked="checked" '; endif;?> /> Habilitar servicio del courier Mobi (el cliente pagará el costo del envío)</label><br/>
                                        <input class="hidden" id="pagopar_mobi_hora_contador" name="pagopar_mobi_hora_contador" value="<?php echo $pagopar_mobi_hora_contador;?>">

                                </fieldset>
                            </td>
                        </tr>
                        <?php
                                    /*woocommerce_wp_select_multiple( array(
                                            'id' => 'newoptions',
                                            'name' => 'newoptions[]',
                                            'class' => 'newoptions',
                                            'label' => __('', 'woocommerce'),
                                            'options' => array(
                                                '1' => 'Lunes',
                                                '2' => 'Martes',
                                                '3' => 'Miércoles',
                                                '4' => 'Jueves',
                                                '5' => 'Viernes',
                                                '6' => 'Sábado',
                                                '7' => 'Domingo',
                                            ))
                                    );*/

                                    ?>
                                    <?php
                                    $pagopar_mobi_hora = get_option('pagopar_mobi_hora');

                                    $pagopar_mobi_hora = json_decode($pagopar_mobi_hora,true);
                                    if(count((array)$pagopar_mobi_hora)<=0){
                                        $pagopar_mobi_hora = 1;
                                    }
                                    ?>
                                    <?php $dias_letras = array('1' => 'L','2' => 'M','3' => 'M', '4' => 'J','5' => 'V','6' => 'S','7' =>'D');?>
                                    <?php
				        if (count((array)$pagopar_mobi_hora)):
									for($var_vertical=0;$var_vertical<count((array)$pagopar_mobi_hora);$var_vertical++):?>
                                    <tr valign="top" class="hora" id="hora_<?php echo ($var_vertical+1);?>">
                                    <th scope="row" class="titledesc">
                                    <label for="pagopar_mobi_activo_general">Días disponibles para entregar el producto</label>
                                    </th>
                                    <td class="forminp">
                                    <fieldset>
                                    <?php $array_dias = explode(',',$pagopar_mobi_hora[$var_vertical]['dias']);?>
                                         <label>Hora Inicio</label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label>Hora Fin</label>  <br>
                                         <select class="select wc-enhanced-select" name="hora_mobi_inicio_<?php echo ($var_vertical+1);?>" id="hora_mobi_inicio_<?php echo ($var_vertical+1);?>" style="" tabindex="-1" aria-hidden="true">
                                             <?php
                                             foreach ($horarios as $key => $value) : ?>
                                                 <option value="<?php echo $value; ?>" <?php if ($pagopar_mobi_hora[$var_vertical]['hora_inicio'] === $value): echo ' selected="selected" '; endif;?> ><?php echo $value; ?></option>
                                             <?php endforeach; ?>
                                         </select>
                                         <select class="seqect wc-enhanced-select" name="hora_mobi_fin_<?php echo ($var_vertical+1);?>" id="hora_mobi_fin_<?php echo ($var_vertical+1);?>" style="" tabindex="-1" aria-hidden="true">
                                             <?php
                                             foreach ($horarios as $key => $value) : ?>
                                                 <option value="<?php echo $value; ?>" <?php if ($pagopar_mobi_hora[$var_vertical]['hora_fin'] === $value): echo ' selected="selected" '; endif;?> ><?php echo $value; ?></option>
                                             <?php  endforeach; ?>
                                         </select>

                                         <div class="franja-dias">
                                             <?php for($var_horizontal=1;$var_horizontal<8;$var_horizontal++):?>
                                                 <?php if(in_array($var_horizontal,$array_dias)==1):?>
                                                     <input  class="hidden" value="<?php echo $var_horizontal;?>" id="dia-<?php echo $var_horizontal;?>-<?php echo ($var_vertical+1);?>" name="dia-<?php echo $var_horizontal;?>-<?php echo ($var_vertical+1);?>"><span attr-val="1" class="dia dia-<?php echo $var_horizontal;?>-<?php echo ($var_vertical+1);?> active" style='background: rgb(154, 154, 161);'><?php echo $dias_letras[$var_horizontal];?></span>
                                                 <?php else:?>
                                                     <input  class="hidden" id="dia-<?php echo $var_horizontal;?>-<?php echo ($var_vertical+1);?>" name="dia-<?php echo $var_horizontal;?>-<?php echo ($var_vertical+1);?>"><span attr-val="1" class="dia dia-<?php echo $var_horizontal;?>-<?php echo ($var_vertical+1);?>"><?php echo $dias_letras[$var_horizontal];?></span>
                                                 <?php endif;?>
                                             <?php endfor;?>
                                         </div>
                                        <br>
                                            <button class="button-primary" type="button" id="agregar">+ AGREGAR HORARIO</button>
                                            <button class="button-primary rojo" type="button" id="borrar" data-value="<?php echo ($var_vertical+1);?>">- BORRAR HORARIO</button>
                                        </fieldset>
                                        </td>
                                        </tr>
                                     <?php endfor;?>
                                 <?php endif; ?>
						<?php endif; ?>
                    <tr>
                        <th colspan="2"><h2>Envio a cargo del comercio</h2></th>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="pagopar_envio_propio_tiempo_entrega">¿En cuánto tiempo te comprometés a entregar los productos comprados? (en horas)</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>¿En cuánto tiempo te comprometés a entregar los productos comprados? (en horas)</span></legend>
                                <label for="pagopar_envio_propio_tiempo_entrega">
                                    <input  class="" type="text" name="pagopar_envio_propio_tiempo_entrega" id="pagopar_envio_propio_tiempo_entrega" style="" value="<?php echo $pagopar_envio_propio_tiempo_entrega;?>" placeholder="Ejemplo: 24" /> </label><br/>
                            </fieldset>
                        </td>
                    </tr>


                </table>
            <?php endif;?>

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

        $(document).on('click','.dia',function(e){
           var clase = e.target.className;
           clase = clase.split(" ");
           clase=clase[1];
           var dia = clase.split("-");
           dia = dia[1];
            if ($('.'+clase).css('background') === 'rgb(154, 154, 161) none repeat scroll 0% 0% / auto padding-box border-box') {
                $('.'+clase).css('background','#ffff');
                $('#'+clase).val(null);
            }else{
                $('.'+clase).css('background','#9a9aa1');
                $('.'+clase).addClass( "dia active" );
                $('#'+clase ).val(dia);
            }
        });

        $(document).on('click','#borrar',function(e){
            var cantida_detalle=$("#pagopar_mobi_hora_contador").val();
            //alert($(this).data("value")+" - "+cantida_detalle);
            if($(this).data("value") && cantida_detalle==1){
                alert("No se puede borrar el horario. Al menos debe existir uno.");
                return
            }

            if(cantida_detalle==$(this).data("value")){
                $('.pagoparClass').remove();
                $("#hora_"+$(this).data("value")).remove();
                var cantida_detalle = $("#pagopar_mobi_hora_contador").val();
                var contador = $("#pagopar_mobi_hora_contador").val();
                contador--;
                $("#pagopar_mobi_hora_contador").val(contador);
                return;
            }else {
                alert("Debes borrar el horario anterior.");
                return;
            }
        });

        var counter = 1;
        $(document).on('click','#agregar',function(e){
            counter = $("#pagopar_mobi_hora_contador").val();
            counter++;
            $("#pagopar_mobi_hora_contador").val(counter);
            var $tr    = $(this).closest('.hora');
            var newClass='pagoparClass';
            var row = $tr.clone().addClass(newClass);
            row.find('input').val('');
            row.find('select').val('');
            $tr.after(row);
            $('.pagoparClass').each(function(index, tr) {
                $(tr).find('td').each (function (index2, td) {

                    $(this).find('input').each(function(index3){
                        $(this).attr('id',"dia-"+(index3+1)+"-"+(counter) );
                        $(this).attr('name',"dia-"+(index3+1)+"-"+(counter) );
                    });

                    $(this).find('select:first').each(function(index4){
                        $(this).attr('id',"hora_mobi_inicio_"+(counter) );
                        $(this).attr('name',"hora_mobi_inicio_"+(counter) );
                    });

                    $(this).find('select:last').each(function(index5){
                        $(this).attr('id',"hora_mobi_fin_"+(counter) );
                        $(this).attr('name',"hora_mobi_fin_"+(counter) );
                    });


                    $(this).find('span').each(function(index5){
                        $(this).attr('class',"clone dia dia-"+(index5+1)+"-"+(counter) );
                        $(this).attr('name',"dia dia-"+(index5+1)+"-"+(counter) );
                    });

                    $(this).find('button').each(function(index6){
                        $(this).attr('data-value',(counter));
                    });

                });
            });
            $('.clone').css('background','#ffff');
            $( "tr pagoparClass" ).addClass( "pagoparClass pagoparClassRemove" );
            $('tr pagoparClass').removeClass('pagoparClass');
            $( ".dia" ).removeClass( "clone" );
        });

    });
</script>

<style>
    .franja-dias .dia {
        -webkit-transition: all 200ms ease;
        -moz-transition: all 200ms ease;
        -o-transition: all 200ms ease;
        -ms-transition: all 200ms ease;
        transition: all 200ms ease;
        display: inline-block;
        width: 32px;
        height: 32px;
        -moz-border-radius: 32px;
        -webkit-border-radius: 32px;
        border-radius: 32px;
        border: 1px solid #c8c8c8;
        vertical-align: middle;
        text-align: center;
        line-height: 32px;
        cursor: pointer;
        margin-top: 5px;
        background: #ffff;
    }

    #borrar{
        background-color: rgba(255, 0, 0, 0.54);
    }

</style>