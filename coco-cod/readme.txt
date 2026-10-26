=== Coco COD — Pedido Contra Entrega para WooCommerce ===
Contributors: denisparaguayo
Donate link: https://github.com/denisparaguayo/cod-cococherry
Tags: woocommerce, cod, cash on delivery, dropi, paraguay, checkout
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Pedido contra entrega en 3 pasos con ubicación primero, flete en vivo por ciudad, compatibilidad nativa con Dropi y personalización de botones.

== Descripción ==

Coco COD es un plugin de alto rendimiento diseñado para tiendas de WooCommerce en Paraguay y Latinoamérica que operan con pago contra entrega (COD) y dropshipping mediante Dropi.

Características principales:
* Modal en 3 pasos ultra-rápido en la ficha de producto.
* Base de datos local completa de departamentos y distritos de Paraguay (sin APIs externas lentas).
* Gestor dinámico de tarifas de envío por ciudad para productos de Stock Propio.
* Compatibilidad 100% con Dropi: productos Dropi a flete Gs. 0 y exclusión automática de Alto Paraguay donde Dropi no tiene cobertura.
* Personalización visual completa de botones (colores, hover, bordes y textos).
* Compatibilidad con HPOS (High-Performance Order Storage).
* Disparo único y nativo de compras en Meta Pixel mediante redirección a la página de gracias estándar.

== Instalación ==

1. Comprime la carpeta `coco-cod` en un archivo `.zip` (o súbela por FTP a `/wp-content/plugins/`).
2. En tu panel de WordPress, ve a **Plugins > Añadir nuevo > Subir plugin**.
3. Selecciona el archivo `.zip` y pulsa **Instalar ahora**.
4. Haz clic en **Activar plugin**.
5. Ve a **WooCommerce > Coco COD** para personalizar los colores y tus tarifas de envío por ciudad.

== Changelog ==

= 1.1.0 =
* Versión inicial estable.
* Modal de 3 pasos con cálculo de flete en vivo.
* Email obligatorio ingresado por el cliente con validación RFC.
* Gestor dinámico de tarifas por ciudad en el panel de administración.
* Compatibilidad con HPOS y WooCommerce 8.x / 9.x.
