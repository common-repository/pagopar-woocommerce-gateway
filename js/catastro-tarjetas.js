jQuery(document).ready( function (e) {

    var $ = jQuery;
    if (typeof urlm === 'undefined')
        return false;

    var updateTimer, dirtyInput = false, xhr;
    $('.pagoparDeleteCard').click(function (e) {
        var data = {
            action: 'pagopar_borrar_tarjeta',
            nonce: urlm.nonce,
            hash_tarjeta: e.target.name,
        };
        xhr = $.ajax({
            type: 'POST',
            url: urlm.ajax_url,
            data: data,
            success: function(response) {
                const json = jQuery.parseJSON(response.substring(0, response.length - 1));
                if (json.respuesta === true){
                    $("#"+e.target.name).remove();
                } else {
                    alert(json.resultado);
                }
            },
            error: function(code){
                alert('Hubo un error al obtener las categorías. Recargue la página e intente nuevamente');
            }
        });
    });
    $('#reversePagoparPay').click(function (e) {
        var data = {
            action: 'pagopar_reversar_pago',
            nonce: urlm.nonce,
            hash_pedido: e.target.name,
        };
        xhr = $.ajax({
            type: 'POST',
            url: urlm.ajax_url,
            data: data,
            success: function(response) {
                const json = jQuery.parseJSON(response.substring(0, response.length - 1));
                if (json.respuesta === true){
                    location.reload();
                } else {
                    alert(json.resultado);
                }
            },
            error: function(code){
                alert('Hubo un error al obtener las categorías. Recargue la página e intente nuevamente');
            }
        });
    });

    $('#pagoparAddCard').click(function (e) {
            
   /*         $('#modalPagoparTarjetas').toggleClass('is-visible');*/
            
            $("#modalPagoparTarjetas").modal({
                closeClass: 'icon-remove',
                closeText: '!'
              });
            /*jQuery('.modal').modal('show');*/
            const data = {
                action: 'pagopar_agregar_tarjeta',
                nonce: urlm.nonce,
            };
            xhr = $.ajax({
                type: 'POST',
                url: urlm.ajax_url,
                data: data,
                success: function(response) {
                    /*const jsonLimpio=  response.substring(0, response.length - 1);*/
                    /*const json = jQuery.parseJSON(response);*/
                    const json = response;
                    /*console.log(json);*/
                    
                    if(json.respuesta === true) {
                        const styles = {
                            'input-background-color' : '#ffffff',
                            'input-text-color': '#333333',
                            'input-border-color' : '#ffffff',
                            'input-placeholder-color' : '#333333',
                            'button-background-color' : '#5CB85C',
                            'button-text-color' : '#ffffff',
                            'button-border-color' : '#4CAE4C',
                            'form-background-color' : '#ffffff',
                            'form-border-color' : '#dddddd',
                            'header-background-color' : '#dddddd',
                            'header-text-color' : '#333333',
                            'hr-border-color' : '#dddddd'
                        };
                        $('.loader-1').css('display', 'none');

                        Bancard.Cards.createForm('iframe-container-pagopar-catrastro', json.resultado, { styles: styles }, function(bancardJson) {
                            const data2 = {
                                action: 'pagopar_confirmar_tarjeta',
                                nonce: urlm.nonce
                            };
                            
                            
                            xhr = $.ajax({
                                type: 'POST',
                                url: urlm.ajax_url,
                                data: data2,
                                success: function(r) {
                                    console.log(r);
                                    location.reload();
                                },
                                error: function(code){
                                    alert('Error al confirmar tarjeta');
                                }
                            });
                            if (bancardJson.message == 'add_new_card_fail')
                                alert(bancardJson.details);

                        });
                        
                    }else{
                        
                        if (json.codigo=='NO_TIENE_CELULAR'){
                            $("#iframe-container-pagopar-catrastro").html('Para catastrar su tarjeta, primero complete los datos faltantes:<br /><label for="" class="">Celular&nbsp;<abbr class="required" title="obligatorio">*</abbr></label><br /><input id="pagopar-catastro-celular" type="text" value="" /><input id="pagopar-catastro-guardar-datos-faltantes" type="button" value="Guardar y seguir" />');
                            /*Si ya ingresó su numero de celular en informacion de facturacion, copiamos de ahí*/
                            /*$("#pagopar-catastro-celular").val($("#billing_phone").val());*/
                            
                        }
                        
                    }
                },
                error: function(code){
                    alert('Ocurrio un error al tratar de agregar una tarjeta');
                }
            }, "json");

        });

    $(".modal-toggle").on("click", function(){
        $('.modal').toggleClass('is-visible');
    });
    
    
    
    

/* Actualizacion de datos faltantes para poder agregar el cliente y luego la tarjeta*/
    jQuery(document).on ("click", "#pagopar-catastro-guardar-datos-faltantes", function () {
        
        var pagoparCatastroCelular = jQuery("#pagopar-catastro-celular").val();
        
        jQuery("#iframe-container-pagopar-catrastro").html('Cargando.. aguarde unos segundos..');

         const data = {
                action: 'pagopar_catastro_guardar_datos_faltantes',
                nonce: urlm.nonce,
                pagopar_catastro_celular: pagoparCatastroCelular
            };
            
        xhr = $.ajax({
                type: 'POST',
                url: urlm.ajax_url,
                data: data,
                success: function(response) {
                    if(response.respuesta == true) {
                        $('.loader-1').css('display', 'none');
                        $( "#pagoparAddCard" ).trigger( "click" );
                    }else{
                        alert(response.resultado);
                        $("#iframe-container-pagopar-catrastro").html('Para catastrar su tarjeta, primero complete los datos faltantes:<br /><label for="" class="">Celular&nbsp;<abbr class="required" title="obligatorio">*</abbr></label><br /><input id="pagopar-catastro-celular" type="text" value="'+pagoparCatastroCelular+'" /><input id="pagopar-catastro-guardar-datos-faltantes" type="button" value="Guardar y seguir" />');
                        
                    }
                },
                error: function(code){
                    alert('Ocurrio un error al tratar de actualizar sus datos.');
                }
            }, "json");
    });
    
    
    
    
});