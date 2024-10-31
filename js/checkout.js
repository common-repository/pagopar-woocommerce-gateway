
jQuery(document).ready( function (e) {
    var $ = jQuery;
    if ( typeof urlm === 'undefined' )
        return false;

    var updateTimer,dirtyInput = false,xhr;

    var original_form = $('#customer_details');
 
 
 
 /*


var markers = [];
function createMarker(coords) {
    var id
    if (markers.length < 1)
        id = 0
    else
        id = markers[markers.length - 1]._id + 1
    var popupContent =
            '<p>Quiero la entrega en esta posición</p>';
    myMarker = L.marker(coords, {
        draggable: false
    });
    myMarker._id = id
    var myPopup = myMarker.bindPopup(popupContent, {
        closeButton: false
    });
    map.addLayer(myMarker)
    markers.push(myMarker)
}


function clearMarkers(id) {
    console.log(markers)
    var new_markers = []
    markers.forEach(function (marker) {
        map.removeLayer(marker)
    })
}

function onMapClick(e) {
    clearMarkers()
    createMarker(e.latlng)
}

 */

    function pagopar_add_fees_action(payment_method) {

        var data = {
            action: 'pagopar_add_fees',
            nonce: urlm.nonce,
            payment:payment_method
        };
       // console.log(urlm);
        $.ajax({
            type: 'POST',
            url: urlm.ajax_url,
            data: data,
            success: function (response) {
                jQuery('.fee').html( null );
                jQuery('.order-total').html( null );
                $("tfoot").append(response);

            },
            error: function (error) {
                console.log("error");
                console.log(error);
            }

        });

    }

    function change_pagopar_checkout_fields(){

        var new_form = $(original_form);

        /*Show ajax loading*/
        $('#customer_details,#order_review').block({message:null,overlayCSS:{background:'#fff url('+urlm.ajax_loader_url+') no-repeat center',backgroundSize:'16px 16px',opacity:0.6} });

        
        var campos = {};
        $('#customer_details input').each(function() {
            campos[jQuery(this).attr('name')] = $(this).val();
        });



        var data = {
            action: 'pagopar_checkout',
            nonce: urlm.nonce,
            campos:  campos
        };
        $.ajax({
            type: 'POST',
            url: urlm.ajax_url,
            data: data,
            success: function( response ) {
                 /*console.log(response);*/
                /*$(new_form).find('.woocommerce-billing-fields').append($(response).find('.woocommerce-billing-fields__field-wrapper').html());*/

                //console.log(response);
                //$( ".fee" ).remove();
                var payment_method = "pagopar";
                if($('#payment_method_cod').is(':checked'))
                {
                    payment_method = "otros"
                }
                pagopar_add_fees_action(payment_method);

                
                if (window.location.hostname=='microsupply.com.py'){
                    $('#customer_details').find('.woocommerce-billing-fields').html(response);
		}  
		else if (window.location.hostname=='skylerstore.com.py' || window.location.hostname=='www.skylerstore.com.py' || window.location.hostname=='www.tiendagraco.com.py'){
                    $('#customer_details').find('.woocommerce-billing-fields').html(response);
		}
                else if (window.location.hostname=='demo.capacitiva.com.py' || window.location.hostname=='capacitiva.com.py' || window.location.hostname=='www.capacitiva.com.py'){
                    $('#customer_details').find('.woocommerce-billing-fields').html(response);
		}
                else if ($('#customer_details .col-1').length==1){

                    if (window.location.hostname=='embassyflores.com.py'){
                        $(new_form).find('.woocommerce-billing-fields').html($(response));
                    }else{
                        $(new_form).find('.col-1').html($(response));
                    }
                   
                }else if ($('#customer_details .col-lg-7').length==1){
                    $(new_form).find('.col-lg-7').html($(response));
                }
                else{
                    $(new_form).find('.woocommerce-billing-fields').html($(response));
                }


               // $(document.body).trigger('update_checkout');
                /**/
                /*$(new_form).find('.woocommerce-billing-fields .woocommerce-billing-fields__field-wrapper').html('');*/



                /**/
                /*$('#customer_details').find('.woocommerce-billing-fields').html($(new_form).find(".woocommerce-billing-fields").html());
                show_city = $(new_form).find("label[for='billing_ciudad'] .required").length;*/
                
                $('#customer_details,#order_review').unblock();
                $.getScript( urlm.js_url )
                    .done(function( script, textStatus ) {
                        console.log( textStatus );
                    })
                    .fail(function( jqxhr, settings, exception ) {
                        console.log( exception );
                });
            },
            error: function(code){
                alert('Hubo un error al cargar los campos. Por favor intente nuevamente');
                $('#customer_details,#order_review').unblock();
            }
        });


        $( "#modalPagoparTarjetas" ).on('shown', function(){
            alert("I want this to appear after the modal has opened!");
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
                    console.log(json);
                    
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
                            $("#pagopar-catastro-celular").val($("#billing_phone").val());
                        }else{
                            $("#modalPagoparTarjetas").modal('hide');
                            alert(json.resultado);
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
            $('.modal').modal('hide');
        });
        
        
        
    }
    
    
    //CAMBIAR ESTA FUNCION
    function restore_normal_fields(){
        /*Show ajax loading*/
        $('#customer_details,#order_review').block({message:null,overlayCSS:{background:'#fff url('+wc_checkout_params.ajax_loader_url+') no-repeat center',backgroundSize:'16px 16px',opacity:0.6} });


        var campos = {};
        $('#customer_details input').each(function() {
            campos[jQuery(this).attr('name')] = $(this).val();
        });
        
        
        var data = {
            action: 'non_pagopar_checkout',
            nonce: urlm.nonce,
            campos: campos
        };
        $.ajax({
            type: 'POST',
            url: urlm.ajax_url,
            data: data,
            success: function( response ) {
                var order_output = $(response);
                /*console.log(response);*/

                var payment_method = "pagopar";
                if($('#payment_method_cod').is(':checked'))
                {
                    payment_method = "otros"
                }
                pagopar_add_fees_action(payment_method);


                $('#customer_details').find('.woocommerce-billing-fields').parent().html(response);
                $('#customer_details,#order_review').unblock();
                $('.shop_table').unblock();
                $('#order_review div').unblock();

            },
            error: function(code){
                alert('Hubo un error al cargar los campos. Por favor intente nuevamente');
                $('#customer_details,#order_review').unblock();
                $('.shop_table').unblock();
                $('#order_review div').unblock();            }
        });
    }


    function change_price_via_ajax(total_price,envio_price){
        $('#order_review').block({message:null,overlayCSS:{background:'#fff url('+wc_checkout_params.ajax_loader_url+') no-repeat center',backgroundSize:'16px 16px',opacity:0.6} });

        var data = {
            action: 'pagopar_checkout_change_price',
            nonce: urlm.nonce,
            envio: envio_price,
            total: total_price,
        };
        $.ajax({
            type: 'POST',
            url: urlm.ajax_url,
            data: data,
            success: function( response ) {
                var div = $("<div>", {id: "price_pagopar_hidden"});
                $(div).text(response);
                $(div).html($(div).text());

                var total = $(div).find("#total").html();
                var envio = $(div).find("#envio").html();

                $('.order-total').find("td").first().html('<strong>'+total+'</strong>');
                $('.cart-subtotal').find("td").first().html('<strong>'+envio+'</strong>');

                $('#order_review').unblock();
            },
            error: function(code){
                alert('Hubo un error al cargar los campos. Por favor intente nuevamente');
                $('#order_review').unblock();
            }
        });
    }

    /*Control when pagopar gateway is in use*/
    function using_pagopar_gateway(){
        if($('form[name="checkout"] input[name="payment_method"]:checked').val().indexOf("pagopar") > -1){
            if($('#pagopar_allow_change').val() == "1"){
                change_pagopar_checkout_fields();;
                $('#pagopar_allow_change').val(0);
            }
        }else{
            restore_normal_fields();
            $('#pagopar_allow_change').val(1);
        }
    }
/*
    jQuery(function(){
        $('form[name="checkout"]').append('<input id="pagopar_allow_change" type="hidden" value="1" />');
        $(document.body).on( 'click', '#order_review', function() {
            using_pagopar_gateway();

            $('input[name="payment_method"]').on('change',function(){
                using_pagopar_gateway();
            });
        });

        if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() == 'pagopar'){
            jQuery('#order_review').trigger("click");
        }

    });
*/

    /*Control when gateway payment changes*/
    jQuery(function(){

        $('form[name="checkout"]').append('<input id="pagopar_allow_change" type="hidden" value="1" />');
        $(document.body).on( 'updated_checkout', function() {
            using_pagopar_gateway();
            
            $('.shop_table').unblock();
            $('.woocommerce-checkout-payment').unblock();

            $('input[name="payment_method"]').on('change',function(){
                using_pagopar_gateway();
            });
        });
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
    
    
 
    
    /*jQuery(document).on ("click", 'input[name="payment_method"]', function () {
        
        if ($(this).val() === 'pagopar_pix') {
            alert('Debes ingresar tu CPF o CPNJ en el campo "documento".');
        }
    });*/


    

});