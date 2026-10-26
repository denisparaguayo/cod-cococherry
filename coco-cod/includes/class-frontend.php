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

		// Redirigir página de carrito y modificar botones de catálogo si el modo exclusivo está activo
		add_action( 'template_redirect', array( $this, 'redirect_cart_if_exclusive' ) );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'filter_catalog_add_to_cart_link' ), 10, 2 );

		// Sobrescribir pantalla de agradecimiento / confirmación para pedidos COD
		add_filter( 'wc_get_template', array( $this, 'override_thankyou_template' ), 20, 5 );
	}

	public function enqueue_frontend_assets() {
		$plugin = Plugin::instance();
		if ( ! $plugin->is_active_for_current_user() ) {
			return;
		}

		$is_exclusive = 'yes' === $plugin->get_setting( 'exclusive_cod_mode', 'yes' );

		// CSS global para ocultar carrito del header si el modo exclusivo está activo
		if ( $is_exclusive ) {
			$header_cart_css = "
			.site-header-item-cart,
			.kadence-header-cart,
			.header-cart,
			.menu-item-cart,
			.widget_shopping_cart,
			.woocommerce-mini-cart,
			.cart-contents,
			.wp-block-woocommerce-mini-cart {
				display: none !important;
			}
			";
			wp_register_style( 'coco-cod-sitewide', false );
			wp_enqueue_style( 'coco-cod-sitewide' );
			wp_add_inline_style( 'coco-cod-sitewide', $header_cart_css );
		}

		// Encolar estilos en la página de agradecimiento / confirmación
		if ( is_order_received_page() ) {
			if ( 'yes' === $plugin->get_setting( 'custom_thankyou', 'yes' ) ) {
				remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
			}

			wp_enqueue_style(
				'coco-cod-css',
				COCO_COD_URL . 'assets/css/coco-cod.css',
				array(),
				COCO_COD_VERSION
			);

			$btn_bg = $plugin->get_setting( 'btn_bg_color', '#16a34a' );
			wp_add_inline_style( 'coco-cod-css', ":root { --coco-btn-bg: {$btn_bg}; }" );
			return;
		}

		if ( ! is_product() ) {
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

		// Ocultar botón nativo de añadir al carrito si modo exclusivo o hide_add_to_cart están activos
		if ( $is_exclusive || 'yes' === $plugin->get_setting( 'hide_add_to_cart', 'no' ) ) {
			$custom_css .= "
			.single_add_to_cart_button,
			form.cart .button.alt:not(#coco-cod-open-modal),
			.woocommerce-variation-add-to-cart .single_add_to_cart_button {
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
				'freeShipping'    => __( 'Envío Gratis', 'coco-cod' ),
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
		$btn_layout   = $plugin->get_setting( 'btn_layout', 'inline' );

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

	/**
	 * Redirige la página de carrito a la tienda si el modo exclusivo COD está activo.
	 */
	public function redirect_cart_if_exclusive() {
		$plugin = Plugin::instance();
		if ( is_cart() && 'yes' === $plugin->get_setting( 'exclusive_cod_mode', 'yes' ) && $plugin->is_active_for_current_user() ) {
			$shop_url = wc_get_page_permalink( 'shop' );
			wp_safe_redirect( $shop_url ? $shop_url : home_url( '/' ) );
			exit;
		}
	}

	/**
	 * Reemplaza el botón de 'Añadir al carrito' por 'Ver producto' en catálogos si el modo exclusivo está activo.
	 */
	public function filter_catalog_add_to_cart_link( $html, $product ) {
		$plugin = Plugin::instance();
		if ( 'yes' === $plugin->get_setting( 'exclusive_cod_mode', 'yes' ) && $plugin->is_active_for_current_user() ) {
			return sprintf(
				'<a href="%s" class="button product_type_simple">%s</a>',
				esc_url( $product->get_permalink() ),
				esc_html__( 'Ver producto', 'coco-cod' )
			);
		}
		return $html;
	}

	/**
	 * Sobrescribe la plantilla de agradecimiento / pedido recibido para pedidos contra entrega.
	 */
	public function override_thankyou_template( $template, $template_name, $args, $template_path, $default_path ) {
		if ( 'checkout/thankyou.php' === $template_name && ! empty( $args['order'] ) ) {
			$order = $args['order'];
			if ( is_numeric( $order ) ) {
				$order = wc_get_order( $order );
			}

			if ( $order instanceof \WC_Order ) {
				$plugin = Plugin::instance();
				if ( 'yes' === $plugin->get_setting( 'custom_thankyou', 'yes' ) ) {
					$custom_template = COCO_COD_PATH . 'templates/thankyou.php';
					if ( file_exists( $custom_template ) ) {
						return $custom_template;
					}
				}
			}
		}
		return $template;
	}
}
