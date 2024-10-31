jQuery(document).ready( function (e) {

    var $ = jQuery;

    $('.pagopar_options.pagopar_tab').click(function(){
        $('.panel.woocommerce_options_panel').each(function(){
            $(this).hide();
        });
        $('#pagopar_product_data').show();
    });



});