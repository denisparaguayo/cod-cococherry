<?php
/**
 * Plugin Name: Coco COD — Pedido Contra Entrega para WooCommerce
 * Plugin URI:  https://github.com/denisparaguayo/cod-cococherry
 * Description: Pedido contra entrega en 3 pasos con ubicación primero, flete en vivo por ciudad, compatibilidad con Dropi y personalización de botones.
 * Version:     1.4.4
 * Author:      Denis Paraguayo
 * Author URI:  https://github.com/denisparaguayo
 * Text Domain: coco-cod
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:      9.5
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

// Versión del Plugin
if ( ! defined( 'COCO_COD_VERSION' ) ) {
	define( 'COCO_COD_VERSION', '1.4.4' );
}

// Rutas base
if ( ! defined( 'COCO_COD_FILE' ) ) {
	define( 'COCO_COD_FILE', __FILE__ );
}

if ( ! defined( 'COCO_COD_PATH' ) ) {
	define( 'COCO_COD_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'COCO_COD_URL' ) ) {
	define( 'COCO_COD_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Declaración explícita de compatibilidad con HPOS (High-Performance Order Storage).
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Carga e inicialización del plugin.
 */
function coco_cod_init() {
	// Verificar si WooCommerce está activo
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Coco COD requiere que WooCommerce esté instalado y activo.', 'coco-cod' ); ?></p>
			</div>
			<?php
		} );
		return;
	}

	// Cargar archivos de soporte
	require_once COCO_COD_PATH . 'includes/class-plugin.php';
	require_once COCO_COD_PATH . 'includes/class-settings.php';
	require_once COCO_COD_PATH . 'includes/class-shipping.php';
	require_once COCO_COD_PATH . 'includes/class-checkout.php';
	require_once COCO_COD_PATH . 'includes/class-frontend.php';
	require_once COCO_COD_PATH . 'includes/class-ajax.php';
	require_once COCO_COD_PATH . 'includes/class-health.php';
	require_once COCO_COD_PATH . 'includes/class-updater.php';

	// Iniciar la instancia principal
	\Coco_COD\Plugin::instance();
}
add_action( 'plugins_loaded', 'coco_cod_init' );

/**
 * Hook de activación para configurar opciones por defecto si no existen.
 */
register_activation_hook( __FILE__, function () {
	// Opciones por defecto
	if ( ! get_option( 'coco_cod_settings' ) ) {
		update_option( 'coco_cod_settings', array(
			'enabled'          => 'yes',
			'btn_text'         => 'Pagar al recibir en casa',
			'btn_subtitle'     => 'Envío rápido · Pago en efectivo',
			'btn_bg_color'     => '#16a34a',
			'btn_text_color'   => '#ffffff',
			'btn_hover_bg'     => '#15803d',
			'btn_hover_text'   => '#ffffff',
			'btn_radius'       => '8px',
			'btn_layout'       => 'inline',
			'shipping_central' => '35000',
			'shipping_interior'=> '45000',
			'city_rates'       => array(
				array( 'state' => '11', 'city' => 'Villeta', 'cost' => '45000' ),
				array( 'state' => '11', 'city' => 'Nueva Italia', 'cost' => '45000' ),
				array( 'state' => '11', 'city' => 'Ypacaraí', 'cost' => '45000' ),
			),
			'allow_reference'  => 'yes',
			'test_mode'        => 'no',
		) );
	}
} );
