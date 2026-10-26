=== Coco COD — Pedido Contra Entrega para WooCommerce ===
Contributors: denisparaguayo
Donate link: https://github.com/denisparaguayo/cod-cococherry
Tags: woocommerce, cod, cash on delivery, dropi, paraguay, checkout
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Pedido contra entrega en 3 pasos con ubicación primero, flete en vivo por ciudad, compatibilidad nativa con Dropi y personalización de botones.

== Descripción ==

Coco COD es un plugin de alto rendimiento diseñado para tiendas de WooCommerce en Paraguay y Latinoamérica que operan con pago contra entrega (COD) y dropshipping mediante Dropi.

Características principales:
* Opción de Transferencia Bancaria / QR para productos de Stock Propio: el cliente puede elegir entre Efectivo Contra Entrega o Transferencia Bancaria directa con datos de cuenta (Ueno) y botón para copiar cuenta.
* Encabezado superior en el modal con título y subtítulo tranquilizador personalizable.
* Pantalla de Agradecimiento y Confirmación moderna: diseño premium de alta conversión con aviso del monto exacto a pagar en efectivo o datos bancarios para transferencia, tracker de estados, resumen visual y botón a WhatsApp.
* Actualizador automático directo desde GitHub: comprueba y actualiza a la última versión con 1 solo clic en el panel sin subir archivos ZIP.
* Modal en 3 pasos ultra-rápido en la ficha de producto.
* Base de datos local completa de departamentos y distritos de Paraguay (sin APIs externas lentas).
* Gestor dinámico de tarifas de envío por ciudad para productos de Stock Propio.
* Modo Exclusivo COD (Venta 1 a 1): desactiva el carrito y oculta botones nativos, completamente reversible desde el panel de ajustes.
* Compatibilidad 100% con Dropi: productos Dropi a flete Gs. 0 y exclusión automática de Alto Paraguay donde Dropi no tiene cobertura.
* Personalización visual completa de botones (colores, hover, bordes y textos).
* Opción para ocultar o mantener el botón nativo de añadir al carrito.
* Compatibilidad con HPOS (High-Performance Order Storage).
* Disparo único y nativo de compras en Meta Pixel mediante redirección a la página de gracias estándar.

== Instalación ==

1. Comprime la carpeta `coco-cod` en un archivo `.zip` (o súbela por FTP a `/wp-content/plugins/`).
2. En tu panel de WordPress, ve a **Plugins > Añadir nuevo > Subir plugin**.
3. Selecciona el archivo `.zip` y pulsa **Instalar ahora**.
4. Haz clic en **Activar plugin**.
5. Ve a **WooCommerce > Coco COD** para configurar y gestionar actualizaciones.

== Changelog ==

= 1.4.0 =
* Nueva función de Transferencia Bancaria / QR para productos de Stock Propio (no Dropi).
* Selector de método de pago en el Paso 3 del modal: Pago Contra Entrega (Efectivo) vs Transferencia Bancaria (Ueno Bank).
* Tarjeta destacada de datos bancarios en la pantalla final de agradecimiento con botón de 1-clic para copiar el número de cuenta.
* Botón de WhatsApp adaptado para enviar el comprobante de transferencia indicando el número de pedido.
* Configuración de datos bancarios disponible en WooCommerce > Coco COD > General.

= 1.3.1 =
* Añadido encabezado superior al modal encima del producto con título ("Pedido Contra Entrega") y subtítulo ("Pagas en efectivo al recibir en tu domicilio 🇵🇾"), configurables en WooCommerce > Coco COD > Apariencia.
* Tarjeta de producto estilizada con borde suave y fondo neutro para mayor elegancia visual.

= 1.3.0 =
* Nueva Pantalla de Agradecimiento Moderna: Reemplaza la pantalla simple por defecto de WooCommerce por un diseño visual optimizado para tiendas COD en Paraguay. Incluye aviso de efectivo exacto a preparar, estado en tiempo real, resumen de productos comprados, datos limpios de entrega y botón directo de contacto por WhatsApp.
* Mantenimiento 100% de la compatibilidad con píxeles de analítica y Meta Ads mediante hooks nativos de WooCommerce.

= 1.2.1 =
* Botón de confirmación simplificado a "CONFIRMAR PEDIDO" sin mostrar monto repetido.
* Solucionado el error "Código postal / ZIP es un campo requerido" haciendo el código postal opcional en Paraguay y suministrando un valor por defecto seguro en el proceso de checkout modal.

= 1.2.0 =
* Nuevo: Pestaña "Actualizaciones (GitHub)" en WooCommerce > Coco COD. Permite comprobar si hay una nueva versión en tu repositorio y actualizarla directamente con 1 solo clic sin tener que descargar ni volver a subir el archivo ZIP.
* Nuevo: Función de forzar sincronización y reinstalación directa desde GitHub.
* Nuevo: Integración con la comprobación nativa de actualizaciones de WordPress.

= 1.1.3 =
* Nuevo: Modo Exclusivo COD (Venta Directa 1 a 1). Oculta botón de añadir al carrito, oculta ícono de carrito en header y redirige la página de carrito a la tienda, permitiendo comprar exclusivamente de a 1 producto con COD. Reversible en cualquier momento con un solo clic desde WooCommerce > Coco COD > General.

= 1.1.2 =
* Corregida la sincronización de nonces en $_REQUEST para el pipeline nativo de process_checkout() (resuelve el error "no pudimos procesar tu pedido").
* Formateo limpio del total en el botón de confirmación sin etiquetas HTML crudas.
* Tarifa de envío dinámica garantizada para prevenir errores de zona vacía.

= 1.1.1 =
* Corrección en wp_enqueue_scripts para resolver el producto con get_queried_object_id() y cargar los scripts JS/CSS del modal correctamente.
* Añadida opción en ajustes para ocultar el botón nativo "Añadir al carrito" si se desea dejar solo el botón COD.
* Normalización total de códigos de departamento a '0' (Capital) y '1'-'17' compatible con zonas nativas de WooCommerce.

= 1.1.0 =
* Versión inicial estable.
