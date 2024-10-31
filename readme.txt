					=== Pagopar - WooCommerce Gateway ===
Contributors: pagopar
Donate link: 
Tags: pagopar, bancard, pix, tarjetas de credito y billeteras electronicas, paraguay
Requires at least: 4.0
Tested up to: 6.4.3
Stable tag: trunk
License: 
License URI: 

Vendé a todo el país con los principales medios de pago.

== Description ==

Pagopar es una solución tecnológica que te permite cobrar con los principales medios de pago de Paraguay: tarjeta de crédito y débito locales (con las procesadoras Bancard, Cabal y Panal), tarjetas de crédito y débito internacionales, bocas de cobranza (Aqui Pago, Pago Express, Wepa) y billeteras electrónicas (Tigo Money, Personal, Zimple, Wally), transferencias bancarias y PIX para usuarios de Brasil.

== Installation ==

Luego de la instalación, ir en ajustes del plugin de Pagopar, y completar los campos que aparecen en dicha página, los datos de clave pública y privada lo obtenés de la página de Pagopar.com, en la sección "Integrar con mi sitio web/app". En esa misma página, hay que setear dos valores inicialmente, que son la URL de respuesta y de redireccionamiento, estos datos lo quitás de la página de ajustes del plugin en tu WordPress.

Luego, deberías simular una venta, para ello, creas un pedido en tu wordpress, te redirige a la web de Pagopar. Luego, estando en tu panel en "Integrar con mi sitio web", sin cerrar la página anterior, y le das en "Simular pago", ejemplo, seleccionando "Practipago", una vez hecho esto, volvés al checkout, elegis "Practipago" y luego click en "Volver al sitio web". Una vez hecho todo esto, ya vas a cumplir los tres pasos necesarios para pasar a producción tu comercio. Te debería marcar chequeado los tres pasos y ahí agregás la IP donde está tu sitio y le das "Pasar a Producción". 

A no olvidar, es importante y es un requisito que los productos de Woocommerce estén asociados a la categoría Pagopar (esto se hace en el tab "Pagopar" en la carga de productos).

== Frequently Asked Questions ==

= ¿Cuál es el requisito para utilizar este plugin? =

El requisito es activar uno de los planes en Pagopar.com/planes. Ya dentro del panel de usuario, en el apartado “Integrar con mi sitio web” tendrás los tokens para configurar este plugin.

= ¿Es necesario asignar la categoría de cada producto para vender? =

La necesidad de asignación depende del tipo de producto que se venderá.
En caso de que se trate de un producto virtual o servicio, no es necesario asignar una categoría a cada producto. Dentro de los ajustes del plugin se debe seleccionar que no se utilizará el envío de AEX y de esa manera se asignará a todos los productos una categoría genérica que desactiva las funciones de envío de Pagopar.

Si se desea utilizar la funcionalidad de AEX, el producto deberá tener definida la categoría Pagopar o las medidas correspondientes. Para esto, hay que ingresar a Woocommerce > Productos, darle editar al producto en cuestión y se debe:
Opción 1: En la pestaña "Pagopar", seleccionar la categoría Pagopar (si pide adicionalmente peso y medidas, también hay que completar).
Opción 2: En la pestaña "Envio", completar el peso y medidas del producto.

Si no se realiza esta operación, los productos afectados no podrán ser vendidos con los couriers ofrecidos por Pagopar (AEX), y no solo eso, sino que anulará la opción de envío con AEX de otros productos que estén en el carrito por más que estos se encuentren bien configurados.

= ¿Cómo cobro mis ventas? =

Las ventas se acreditan en la tarjeta prepaga Pagopar Card o en la cuenta bancaria del banco de tu preferencia (las opciones de acreditación dependen del plan que tengas activo).


= ¿Cuáles son los medios de envío disponibles? =

Los medios de envío disponibles son:
a. Servicio de entrega regular de AEX (24 a 48 hs) - cobertura nacional.
b. Servicio Bummer de AEX (entrega rápida). Disponible en Asunción y Gran Asunción.
En los puntos a y b, el flete se calcula de acuerdo al peso y volumen del producto así como la distancia entre pick up y el local de entrega.   
c. Entrega en e-lockers. Ubicación de los mismos: https://www.aex.com.py/web/centros-atencion.php
d. Funcionalidad para que configures tu propio medio de envío.


= ¿Puedo vender productos virtuales o servicios? =

Si, puedes cobrar productos físicos, productos virtuales y servicios. En caso de productos físicos puedes configurar el servicio de entrega de AEX, tu propio envío o ambos medios de entrega a la vez. 

== Screenshots ==

1. Configuración sencilla para integrar tu sitio Woocommerce con tu cuenta Pagopar
2. Ofrecé todos los medios de pagos a tus clientes
3. Ofrecé a tus clientes la posibilidad de recibir sus compras mediante los distintos servicios de entrega ofrecidos por AEX
4. Tus ventas se acreditan en tu tarjeta Pagopar Card o en tu cuenta bancaria

== Changelog ==

= 2.7.1 =
* Agregado: Se agrega opción para descachear
* Modificado: Se modifica titulo de tarjetas de crédito

= 2.7.0 =
* Agregado: Se agrega medio de pago uPay

= 2.6.9 =
* Agregado: Se agrega funcionalidad para promociones de tarjetas de credito.

= 2.6.8 =
* Modificado: Cambios menores.

= 2.6.7 =
* Agregado: Se agrega nuevamente medio de pago PIX.

= 2.6.6 =
* Modificado: Se soluciona problema al crear pedido.

= 2.6.5 =
* Agregado: Se agrega nuevamente medio de pago QR.
* Modificado: Cambios menores.

= 2.6.4 =
* Modificado: Se modifica URL de API.
* Modificado: Se soluciona problema con PHP 8 en una funcion.

= 2.6.3 =
* Modificado: Se optimizan peticiones referente al dato del comercio.

= 2.6.2 =
* Modificado: Se optimizan peticiones.


= 2.6.1 =
* Modificado: Se agrega mayor compatibilidad con PHP 8 y mejoras varias.


= 2.6 =
* Agregado: Se agrega medio de pago QR.
* Modificado: Se agrega mayor compatibilidad con PHP 8 y mejoras varias.

= 2.5.19 =
* Modificado: Se soluciona problema al sumar total en el checkout.
* Modificado: Se agrega mayor compatibilidad con PHP 8.

= 2.5.18 =
* Modificado: Se soluciona problema de sincronizacion que tenian los productos con descuento.

= 2.5.17 =
* Modificado: Se agrega una nueva excepcion para mitigar problema por distinta estructura en la maquetaciion que afecta a ciertos sitios.

= 2.5.16 =
* Modificado: Se agrega excepcion para mitigar problema por distinta estructura en la maquetaciion que afecta a ciertos sitios.

= 2.5.15 =
* Agregado: Se agrega compatibilidad con plugins que utilizan cantidades decimales como WooCommerce Advanced Quantity.

= 2.5.14 =
* Agregado: Se agrega soporte para PHP 8 probado en funciones basicas.
* Modificado: Soluciones de problemas menores.

= 2.5.13 =
* Agregado: Se agrega nueva forma de pago Giros Claro.

= 2.5.12 =
* Modificado: Se soluciona error al obtener el total de envio para agregar gastos administrativos.

= 2.5.11 =
* Modificado: Se soluciona conflicto con nueva versión de Woocommerce que agrega un fee llamado Cuota.
* Agregado: Se agrega nueva forma de pago Wepa.

= 2.5.10 =
* Modificado: Se soluciona problema del mapa cuando no tiene habilitado la opcion de mobi.


= 2.5.9 =
* Agregado: Se agrega la opcion de Mobi como courier.
* Modificado: Se soluciona problema al mostrar el total en el checkout cuando se utilizaba couriers ofrecidos por Pagopar y gastos administrativos.

= 2.5.8 =
* Agregado: Se agrega posibilidad de sumar un monto adicional como gasto administrativo.
* Modificado: Se controla el numero de cedula antes de crear el pedido.

= 2.5.7 =
* Agregado: Se agrega medio de pago transferencias bancarias y Wally.

= 2.5.6 =
* Agregado: Se agrega funcionalidad de sincronizar productos utilizando el importador de Woocommerce.

= 2.5.5 =
* Modificado: Se soluciona problema al exportar la URL de las imagenes que se daba en ciertos casos.
* Modificado: Se soluciona problema al generar pedido y utilizar impuestos que se daba en ciertos casos.
* Modificado: Se agrega compatibilidad al momento de recibir notificacion de pago cuando esta habilitado el plugin WooCommerce Sequential Order Numbers Pro
* Agregado: Se agrega menu base para configuracion avanzada.


= 2.5.4 =
* Modificado: Se soluciona problema al cargar formulario en checkout.

= 2.5.3 =
* Modificado: Se soluciona problema de cacheo de datos de formas de pago y firma de contrato.
* Modificado: Se soluciona problema de duplicacion de campos en finalizar compra.

= 2.5.2 =
* Modificado: Se muestra correctamente el precio de envio en Checkout cuando se cambia de ciudad.

= 2.5.1 =
* Modificado: Se soluciona problema que hacia no mostrar mensaje de error a la hora de catastrar tarjeta si el numero de telefono no era el formato correcto o error similar.
* Modificado: Se soluciona problema de calcular flete cuando los pesos eran guardados con comas en lugar de punto.
* Modificado: Se soluciona problema de sincronizacion, se enviaba monto anterior si se editaba un producto desde opción "Edición rápida".
* Modificado: Se respetan los valores de alto, largo, ancho y peso la primera vez que se exportan los productos.

= 2.5 =
* Modificado: Se soluciona problema de css que agregaba un margen en la descripcion los medios de pagos finales en el Checkout.
* Agregado: Se agregan datos de configuración del comercio.

= 2.4.2 =
* Modificado: Se soluciona problema al mostrar el numero de telefono al actualizar una direccion especifica, se amplia la cantidad de caracteres de telefono a 13.

= 2.4.1 =
* Modificado: Se soluciona el problema que se daba en la pagina de checkout cuando se compraba un producto fisico, al cambiar la region/provincia, no actualizaba los medios de envio.

= 2.4 =
* Modificado: Se aplica la sincronización de productos con precio descuento.
* Modificado: Se soluciona problema que se exportaban productos con etiqueta de Agotados.

= 2.3.4 =
* Modificado: Se aplica solución realmente al problema que ocasionaba que no se muestren los medios de pagos finales.

= 2.3.3 =
* Modificado: Se soluciona problema que ocasionaba que no se muestren los medios de pagos finales.

= 2.3.2 =
* Modificado: Se agrega problema de calcular flete cuando se utilizaba direccion unica para todos los productos.
* Modificado: Se optimiza la carga de css en el checkout.
* Modificado: Se corrige prefijo de tabla en pagina de chequeo.
* Agregado: Se agregan mas controles de configuracion general del sitio.

= 2.3.1 =
* Agregado: Se agrega chequeo base de configuracion del sitio para determinar posibles problemas de configuraciones generales.

= 2.3 =
* Modificado: Se optimiza peticion de agregar tarjeta en Checkout.
* Modificado: Se soluciona problema al mostrar catastrar tarjeta en Mis tarjetas
* Modificado: Se soluciona problema al mostrar catastrar tarjeta en Checkout
* Modificado: Se soluciona problema al mostrar tarjetas catastradas en Checkout
* Modificado: Se soluciona problema al mostrar tarjetas catastradas en Mis Tarjetas
* Modificado: Se mejora la experiencia al catastrar tarjeta cuando no se tenia cargado el numero de telefono del usuario
* Agregado: Se agrega codigo base para compatibilidad con el plugin Woocommerce Deposits

= 2.2.1 =
* Agregado: Se agrega link de edicion de producto en pagina de chequeo para mas facil acceso a solucionar el problema.

= 2.2 =
* Modificado: Se soluciona problemas en ciertas consultas cuando el prefijo de tabla de Wordpress es distinto a wp que afectaba a sincronizacion
* Modificado: Se optimiza peticiones de obtención de tarjetas catastradas
* Modificado: Se optimiza peticiones de obtención de comercios hijos
* Modificado: Se soluciona problema de asignación de ciudad en la dirección del vendedor
* Modificado: Se soluciona problema de creacion de pedido cuando se utilizaba AEX y se compraba mas de un producto
* Modificado: Se soluciona problema de UX en funcionalidad de catastro de tarjetas, ahora es solo visible si el usuario esta logueado
* Modificado: Se soluciona problema de calculo de flete cuando existen mas de un producto en el carrito con direccíon de retiro distintos, se enviaba la misma dirección
* Modificado: Se soluciona problema de calculo de flete cuando existen mas de un producto en el carrito con direccíon de retiro iguales
* Modificado: Se soliciona problema al mostrar el número de cedula al finalizar el pedido cuando se seleccionaba bocas de cobranza
* Modificado: Se soluciona mostrar número de cedula en pagina de datos del pedido cuando el usuario no está logueado

= 2.1.4 =
* Agregado: Se agrega amplia la funcion de chequeo de productos.
* Modificado: Se soluciona problema de direccion vacía del comprador que se mostraba cuando se usaba AEX en ciertas situaciones.
* Modificado: Se soluciona problema de duplicación de opciones de envio.
* Modificado: Se soluciona problema que ocasionaba incompatibilidad con el plugin WooCommerce Sequential Order Numbers Pro al momento de mostrar pa pagina de datos de pedido.

= 2.1.3 =
* Modificado: Se soluciona problema que ocasionaba incompatibilidad con el plugin WooCommerce Sequential Order Numbers Pro.

= 2.1.2 =
* Modificado: Se soluciona exportacion de productos cuando el prefijo de la tabla de Wordpress era distinto a wp_.
* Modificado: Se soluciona exportacion de productos que se han modificado en otras plataformas agregando envio propio.
* Modificado: Se soluciona reemplazo de formulario de billing que no funcionaba con algunos templates.
* Agregado: Se agrega funcion base para chequeo de productos, de tal forma de determinar configuraciones de productos con problemas.

= 2.1.1 =
* Modificado: Se soluciona migracion de direcciones con numero de telefono.

= 2.1.0 =
* Modificado: Se soluciona problema en pagina de administracion de direcciones que también afectaba a funcion de sincronizacion.

= 2.0.9 =
* Agregado: Se agregan varias opciones de envio ofrecidas por AEX.
* Agregado: Se agrega la posibilidad de agregar prefijo al numero de orden para evitar duplicacion de ID.
* Modificado: Se modifican URLs de imagenes que causaban sitio no seguro.

= 2.0.8 =
* Modificado: Se soluciona problemas con los campos ruc y razon social que no se guardaban.

= 2.0.7 =
* Modificado: Se soluciona problemas migracion de direcciones.

= 2.0.6 =
* Modificado: Se soluciona problemas de direccion de retiro en AEX.
* Agregado: Se agrega soporte parcial de descuentos via cupones.

= 2.0.5 =
* Modificado: Se soluciona problemas de creacion de pedidos adicionales con monto 0.

= 2.0.4 =
* Modificado: Se soluciona problemas al mostrar pestañas en Datos del producto.

= 2.0.3 =
* Modificado: Se soluciona problemas de sincronizacion en sitios que tienen prefijo distinto a wp_ en su configuración de base de datos.

= 2.0.2 =
* Modificado: Se soluciona problema de generacion de pedidos cuando se utilizaba el modulo de envio de Woocommerce sin tener activo couriers de Pagopar.

= 2.0.1 =
* Modificado: Se aplican soluciones de estilos en el checkout.

= 2.0.0 =
* Modificado: Es una actualizacion importante: se modifica la forma en la cual se utiliza AEX por lo cual implica hacer unos pasos manualmente para volver a configurar dicha funcionalidad.
* Modificado: Cambia la forma en la cual se muestran los medios de pagos en el checkout, siendo mucho más amigable para el usuario final

= 1.5.3 =
* Modificado: Se disminuye la cantidad de productos para importar en lote.

= 1.5.2 =
* Modificado: Se soluciona error al catastrar tarjeta.

= 1.5.1 =
* Modificado: Se soluciona error al actualizar el estado de pago que sucedían en ciertas condiciones.

= 1.5 =
* Agregado: Se agrega funcionalidad de sincronizacion de productos.

= 1.4.3 =
* Modificado: Se soluciona problema que hacía que se muestre 0 en total de envio

= 1.4.2 =
* Agregado: Se agrega catastro de tarjetas
* Agregado: Se agrega reversión de pedidos en Tarjetas de crédito, Zimple.
* Modificado: Mejoras varias en configuración

= 1.4.1 =
* Modificado: Se soluciona estilos css
* Modificado: Se soluciona funcion get_query_var no definida

= 1.4.0 =
* Modificado: Se soluciona valores por defecto del footer

= 1.3.9 =
* Agregado: Se agrega footer
* Agregado: Se agrega funcionalidad Split Billing
* Modificado: Se soluciona compatibilidad de algunos templates en el checktout al momento del loading

= 1.3.8 =
* Modificado: Se soluciona compatibilidad producto con cantidades decimales
* Modificado: Se soluciona opción de volver a intentar pagar un pedido ya creado desde Mi cuenta - Pedidos
* Agregado: Se agrega clase css para ocultar input hidden en caso de necesitar
* Agregado: Se agrega menu Pagopar y base de funciones para la proxima versión 2.0 del plugin

= 1.3.7 =
* Modificado: Se soluciona compatibilidad de opción retiro de sucursal en productos variables

= 1.3.6 =
* Agregado: Se guardan los valores de razon social y ruc por orden
* Agregado: Se agrega seteo de valores por defecto de razon social y ruc

= 1.3.5 =
* Modificado: Se mantienen los valores de los campos de checkout al cambiar de metodo de pago

= 1.3.4 =
* Modificado: Se agrega compatibilidad de producto virtual/descargable de Woocommerce con Pagopar cuando existe producto con AEX
* Modificado: Se agrega compatibilidad de producto con AEX con Taxes
* Agregado: Se agrega opción para determinar con qué estado inicial se queda al confirmar un pedido
* Agregado: Se agrega opción para determinar con qué estado inicial se queda al pagar un pedido

= 1.3.3 =
* Modificado: Se agrega soporte para Woocommerce 3.9.0

= 1.3.2 =
* Modificado: Soporte envio en producto variable.

= 1.3.1 =
* Agregado: Se actualiza los datos de direccion de envio en Woocommercet.
* Modificado: Se guarda el costo de envio que proviene del plugin Pagopar.
* Agregado: Se muestran solo los medios de pago disponibles segun cuenta del comercio.

= 1.3.0 =
* Modificado: Se soluciona problema con productos variables en checkout.

= 1.2.9 =
* Modificado: Se soluciona problema con dato de documento.

= 1.2.8 =
* Agregado: Se agrega la posibilidad de tomar de otros campos los datos de documento.

= 1.2.7 =
* Agregado: Se agrega la posibilidad de tomar de otros campos los datos de razon social y RUC.

= 1.2.6 =
* Modificado: Si solo se tiene un medio de envio, al dar Calcular envio, en lugar de calcular el envío se confirmaba el pedido.

= 1.2.5 =
* Modificado: Se soluciona problema que cuando se daba clic en Calcular envío la segunda vez, si estaban todos los datos correctos, en lugar de calcular el envío se confirmaba el pedido.

= 1.2.4 =
* Modificado: Se soluciona problema que hay con billing_state y select2.

= 1.2.3 =
* Modificado: Se soluciona un problema de compatibilidad con la actualizacion de Woocommerce en su version 3.6.2.

= 1.2.2 =
* Modificado: Se soluciona un problema de maquetado en la pagina de "gracias-por-su-compra".

= 1.2.1 =
* Modificado: Se reemplaza el contenido de la descripcion general del pedido.

= 1.2.0 =
* Agregado: Se agrega soporte para base de datos con puertos que no sean por defecto.

= 1.1.9 =
* Agregado: Se muestra la forma de pago y el numero de pedido en el backend de pedidos.

= 1.1.8 =
* Modificación: Se mejoran mensajes de respuesta según sea el medio de pago.

= 1.1.7 =
* Modificación: Se guardan los datos de CI, Razon Social y RUC.
* Agregado: Se muestran los íconos de las entidades entidades financieras adheridas a Pagopar.

= 1.1.6 =
* Modificación: No se notifican algunos errores PHP.

= 1.1.5 =
* Agregado: Se agrega medio de pago Billetera Personal.
* Agregado: Se agrega la posibilidad de modificar la descripción y URL de la imagen de los medios de pago.

= 1.1.4 =
* Modificación: Se modifican los montos mínimos de Aqui Pago y Pagoexpress.

= 1.1.3 =
* Modificación: Se agrega soporte a medio de envio de Woocommerce cuando es gratuito.

= 1.1.2 =
* Agregado: Se agrega medio de pago Tigo Money.

= 1.1.0 =
* Modificación: Se ocultan datos innecesarios.

= 1.0.9 =
* Agregado: Se muestran medios de pago teniendo en cuenta los montos mínimos.
* Agregado: Se muestran medios de pago según opción de asumir costos de montos mínimos.
* Modificación: Se muestra correctamente el precio si es una oferta de un producto variable.

= 1.0.8 =
* Modificación: Correcciones de estilos en el label de los campos del checkout.

= 1.0.7 =
* Modificación: Correcciones y mejoras UI/UX en el checkout.

= 1.0.6 =
* Modificación: Se muestra dirección de envío correctamente.

= 1.0.5 =
* Agregado: Se agrega soporte de impuestos de Woocommerce.
* Agregado: Se agrega la opción para forzar mostrar la dirección independiente a que la categoría asociada al producto soporte o no delivery.
* Modificación: Se soluciona error que mostraba campo innecesario en checkout.

= 1.0.4 =
* Agregado: Se agrega soporte de productos variables.
* Modificación: Se soluciona error de sintaxis al preguntar por un pedido cancelado.

= 1.0.3 =
* Modificación: Se soluciona conflicto de clase CSS.

= 1.0.2 =
* Agregado: Se agrega la posibilidad para mostrar todos los medios de pagos en la página del checkout de Woocommerce y que redireccione a los medios de pago finales sin que se vea el checkout de Pagopar

== Upgrade Notice ==
