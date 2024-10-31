jQuery(document).ready( function (e) {

	var $ = jQuery;
	if ( typeof urlm === 'undefined' )
		return false;

	var updateTimer,dirtyInput = false,xhr;

	function show_pagopar_categories(id_cat,level,padres){
		var data = {
			action: 'pagopar_categories',
			nonce: urlm.nonce,
			id_cat: id_cat,
			level: level,
			padres: padres,
		};
		xhr = $.ajax({
			type: 'POST',
			url: urlm.ajax_url,
			data: data,
			beforeSend:  function(){
				$('#selects_categories_pagopar .ajax_chosen_select_products').each(function(){
					if(parseInt($(this).attr("level")) > level ){
						$(this).remove();
					}
				});
				$('#selects_categories_pagopar .spinner').css('padding','35px 0 10px 50px');
				$('#selects_categories_pagopar .spinner').addClass('is-active');
			},
			success: function( response ) {
				var order_output = $(response);
				next = level + 1;
				$('#selects_categories_pagopar .spinner').removeClass('is-active');
				$('#selects_categories_pagopar .spinner').css('padding','0px');
				$("select.ajax_chosen_select_products[level='"+level+"']").after(""+
					"<select id='product_field_type"+level+"' name='product_field_type"+level+"[]' class='ajax_chosen_select_products'"+
						"style='width:30%' multiple='multiple' level='"+next+"'  onchange='verificarSelect(\"product_field_type"+level+"\");'>"+
						response+
					"</select>");
			},
			error: function(code){
				alert('Hubo un error al obtener las categorías. Recargue la página e intente nuevamente');
			}
		});
	}

	/*Control when option is clicked*/
	$( '#selects_categories_pagopar' ).on('click','.ajax_chosen_select_products option', function(e) {

		var level = parseInt($(this).parent().attr('level'));
		var nuevo_level = level-1;
		var selectObj = document.getElementById('product_field_type'+nuevo_level);
		if( validate_select(selectObj) ){

			var last_level = parseInt($('#selects_categories_pagopar .ajax_chosen_select_products').last().attr('level'));

			if(parseInt($(this).attr('hijos'))){
				var cat_id = $(this).val();
				var padres = [];
				$('#selects_categories_pagopar .ajax_chosen_select_products').each(function(){
					pad = parseInt($(this).val());
					if(!isNaN(pad) && $(this).attr('level')<=level){
						padres.push(pad);
					}
				});
				$("#can_save_val").val("0");
				$("#level_to_save_val").val(level-1);
				$("#pagopar_final_cat").val( "0" );
				show_pagopar_categories(cat_id,level,padres);
			}else{
				if(level<=last_level){
					$('#selects_categories_pagopar .ajax_chosen_select_products').each(function(){
						if(parseInt($(this).attr("level")) > level ){
							$(this).remove();
						}
					});
				}
				/*If doesn't have children, we can save*/
				$("#can_save_val").val('1');
				$("#level_to_save_val").val(level-1);
				$("#pagopar_final_cat").val( parseInt($(this).val()) );
			}
                        
                        if (parseInt($(this).attr('productoFisico'))==1){

                            
                            jQuery('#_virtual').prop("checked", false).trigger("change");


                            jQuery("<style type='text/css'> #woocommerce-product-data .type_box label[for=_virtual] { display: none !important;} </style>").appendTo("head");
                            jQuery("<style type='text/css'> #woocommerce-product-data .type_box label[for=_downloadable] { display: none !important;} </style>").appendTo("head");
                            
                            

                            
                        }else{
                            
                            jQuery('#_virtual').prop("checked", true).trigger("change");

                            jQuery("<style type='text/css'> #woocommerce-product-data .type_box label[for=_virtual] { display: inline !important;} </style>").appendTo("head");
                            jQuery("<style type='text/css'> #woocommerce-product-data .type_box label[for=_downloadable] { display: inline !important;} </style>").appendTo("head");                            
                        }
                        
			/*Show/Hide weight and dimensions fields*/
			if(!parseInt($(this).attr('medidas')) && parseInt($("#product_field_type0").val()) == 906){//Medidas igual a cero y select igual a Productos
				$('#pagopar_product_data .product_weight_field').show();
				$('#pagopar_product_data .dimensions_field').first().show();
                                
                                
			}else{
				$('#pagopar_product_data .options_group .product_weight_field').hide();
				$('#pagopar_product_data .dimensions_field').first().hide();

                                
			}

		}else{
			e.preventDefault();
		}

	});

	$('.pagopar_options.pagopar_tab').click(function(){
		$('.panel.woocommerce_options_panel').each(function(){
			$(this).hide();
		});
		$('#pagopar_product_data').show();
	});

	$('#product_seller_ciudad').on('change', function(){
		new_text_propio = $('#product_seller_ciudad option:selected').text();
		$('#desdePropio').text(new_text_propio);
		$('.envio_propio').each(function(){
			$(this).find('.origin_envio_propio').text(new_text_propio);
		});
	});
	$('#agregarSoporteEnvio').on('click', function(){
		envios = [];
		origen_id = $('#product_seller_ciudad').val();
		origin_name = $('#product_seller_ciudad option:selected').text();
		destino_id = $('#product_direccion_ciudad_todas').val();
		destino_name = $('#product_direccion_ciudad_todas  option:selected').text();
		costo = $('#product_monto_envio').val();
		tiempo = $('#product_horas').val();
		if(origen_id && destino_id && costo && costo != "" && tiempo && tiempo > 0){
			coincidencia = false;
			$('.envio_propio').each(function(index){
				var o = $(this).attr('o');
				var d = $(this).attr('d');
				var c = $(this).attr('c');
				var t = $(this).attr('t');
				if(origen_id == o && destino_id == d && costo == c && tiempo == t){
					coincidencia = true;
					return;
				}
				envios[index] = [d,c,t];
			});
			if(!coincidencia){
				var id = destino_id+"_"+costo+"_"+tiempo;
				envios.push([destino_id,costo,tiempo]);
				$('#envios_propios_array').val(JSON.stringify(envios));
				$('#envios_propios_seleccionados').append("<p class='envio_propio' id='"+id+"' o='"+origen_id+"' d='"+destino_id+"' c='"+costo+"' t='"+tiempo+"'>"+
					"Enviar tu producto de <span class='origin_envio_propio'>"+origin_name+"</span> "+
					"a "+destino_name+" le costará al cliente "+costo+" Gs. adicionales en "+tiempo+" Hs. "+
					"<a class='delete_envio_propio' style='color:#a00;padding-left:7px;cursor:pointer;text-decoration:underline;'>Eliminar</a>"+
				 "</p>");
			}else{
				alert('Ya existe una opción de envío con estas características');
			}
		}else{
			alert('Por favor, completa correctamente todos los datos de envío');
		}
	});

	$("#envios_propios_seleccionados").on('click','.delete_envio_propio',function(e){
		e.preventDefault();
		var delete_envio_option = "#"+$(this).parent().attr('id');
		delete_origen_id = $(delete_envio_option).attr('o');
		delete_destino_id = $(delete_envio_option).attr('d');
		delete_costo = $(delete_envio_option).attr('c');
		delete_tiempo = $(delete_envio_option).attr('t');
		console.log(delete_envio_option);
		new_envios = JSON.parse($('#envios_propios_array').val());
		$(new_envios).each(function(index){
			var d = $(this)[0];
			var c = $(this)[1];
			var t = $(this)[2];
			if(delete_destino_id == d && delete_costo == c && delete_tiempo == t){
				new_envios.splice(index, 1);
				return;
			}
		});
		console.log(new_envios);
		$('#envios_propios_array').val(JSON.stringify(new_envios));
		$(delete_envio_option).remove();
	});

});


function verificarSelect(name){
	var selectObj = document.getElementById(name);
	var level = parseInt(jQuery("#"+name).attr('level'));
	if (validate_select(selectObj, level, name)){
		jQuery( '#selects_categories_pagopar' ).click();
	}
}

function validate_select(select, level, name) {
	var max = 1;
	var count = 0;
	for (var i = 0; i < select.options.length; i++) {
	    if (select.options[i].selected === true)
	        count++;
	}
	if (count > max) {
		jQuery("#can_save_val").val("0");
		jQuery("#level_to_save_val").val(level-1);
		jQuery("#pagopar_final_cat").val("0");
		alert('Solo puede seleccionar 1 categoria.');
	    unselect_everything_after_this_select(level,name);
	    return false;
	}
	return true;
}

function unselect_everything_after_this_select(level,name){
	jQuery("#selects_categories_pagopar .ajax_chosen_select_products").each(function(){
		if(parseInt(jQuery(this).attr('level')) > level){
			jQuery(this).remove();
		}
	});
	jQuery("#"+name+" option:selected").removeAttr("selected");
}