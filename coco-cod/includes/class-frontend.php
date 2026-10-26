<?php
namespace Coco_COD;

defined( 'ABSPATH' ) || exit;

/**
 * Gestión del renderizado en frontend (botón, modal, estilos y scripts).
 */
class Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_cod_button' ), 20 );
		add_action( 'wp_footer', array( $this, 'render_cod_modal' ) );
	}

	public function enqueue_frontend_assets() {
		if ( ! is_product() ) {
			return;
		}

		$plugin = Plugin::instance();
		if ( ! $plugin->is_active_for_current_user() ) {
			return;
		}

		$product_id = get_queried_object_id();
		$product    = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			global $product;
		}
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		// CSS principal
		wp_enqueue_style(
			'coco-cod-css',
			COCO_COD_URL . 'assets/css/coco-cod.css',
			array(),
			COCO_COD_VERSION
		);

		// Variables CSS dinámicas para colores y bordes
		$btn_bg     = $plugin->get_setting( 'btn_bg_color', '#16a34a' );
		$btn_color  = $plugin->get_setting( 'btn_text_color', '#ffffff' );
		$btn_h_bg   = $plugin->get_setting( 'btn_hover_bg', '#15803d' );
		$btn_h_col  = $plugin->get_setting( 'btn_hover_text', '#ffffff' );
		$btn_rad    = $plugin->get_setting( 'btn_radius', '8px' );

		$custom_css = "
		:root {
			--coco-btn-bg: {$btn_bg};
			--coco-btn-color: {$btn_color};
			--coco-btn-hover-bg: {$btn_h_bg};
			--coco-btn-hover-color: {$btn_h_col};
			--coco-btn-radius: {$btn_rad};
		}
		";

		// Opción para ocultar el botón nativo de añadir al carrito
		if ( 'yes' === $plugin->get_setting( 'hide_add_to_cart', 'no' ) ) {
			$custom_css .= "
			.single_add_to_cart_button,
			form.cart .button.alt:not(#coco-cod-open-modal) {
				display: none !important;
			}
			";
		}

		wp_add_inline_style( 'coco-cod-css', $custom_css );

		// JS principal
		wp_enqueue_script(
			'coco-cod-js',
			COCO_COD_URL . 'assets/js/coco-cod.js',
			array( 'jquery' ),
			COCO_COD_VERSION,
			true
		);

		// Pasar parámetros a JavaScript
		$is_dropi = $plugin->is_dropi_product( $product );

		wp_localize_script( 'coco-cod-js', 'cocoCodParams', array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'coco_cod_nonce' ),
			'locations'      => $plugin->get_locations(),
			'isDropi'        => $is_dropi,
			'currencySymbol' => get_woocommerce_currency_symbol(),
			'productId'      => $product->get_id(),
			'productName'    => $product->get_name(),
			'productPrice'   => (float) $product->get_price(),
			'isVariable'     => $product->is_type( 'variable' ),
			'i18n'           => array(
				'selectVariation' => __( 'Por favor selecciona todas las opciones del producto antes de continuar.', 'coco-cod' ),
				'fillFields'      => __( 'Por favor completa todos los campos obligatorios.', 'coco-cod' ),
				'invalidEmail'    => __( 'Por favor ingresa un correo electrónico válido.', 'coco-cod' ),
				'invalidPhone'    => __( 'Por favor ingresa un número de celular válido de Paraguay.', 'coco-cod' ),
				'calcShipping'    => __( 'Calculando envío...', 'coco-cod' ),
				'freeShipping'    => __( 'Envío Gratis (Dropi)', 'coco-cod' ),
				'processingOrder' => __( 'Procesando tu pedido...', 'coco-cod' ),
				'confirmOrder'    => __( 'Confirmar pedido', 'coco-cod' ),
				'errorOrder'      => __( 'Hubo un error al procesar tu pedido. Por favor intenta de nuevo.', 'coco-cod' ),
			),
		) );
	}

	public function render_cod_button() {
		$plugin = Plugin::instance();
		if ( ! $plugin->is_active_for_current_user() ) {
			return;
		}

		global $product;
		if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return;
		}

		$btn_text     = $plugin->get_setting( 'btn_text', 'Pagar al recibir en casa' );
		$btn_subtitle = $plugin->get_setting( 'btn_subtitle', 'Envío rápido · Pago en efectivo' );

		include COCO_COD_PATH . 'templates/button.php';
	}

	public function render_cod_modal() {
		if ( ! is_product() ) {
			return;
		}

		$plugin = Plugin::instance();
		if ( ! $plugin->is_active_for_current_user() ) {
			return;
		}

		$product_id = get_queried_object_id();
		$product    = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			global $product;
		}
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$locations       = $plugin->get_locations();
		$is_dropi        = $plugin->is_dropi_product( $product );
		$allow_reference = 'yes' === $plugin->get_setting( 'allow_reference', 'yes' );

		include COCO_COD_PATH . 'templates/modal.php';
	}
}
