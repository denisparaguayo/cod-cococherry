<?php
namespace Coco_COD;

defined( 'ABSPATH' ) || exit;

/**
 * Optimización y soporte para el Checkout de WooCommerce.
 */
class Checkout {

	public function __construct() {
		// Ajuste de campos de dirección
		add_filter( 'woocommerce_default_address_fields', array( $this, 'customize_address_fields' ) );
		add_filter( 'woocommerce_billing_fields', array( $this, 'customize_billing_fields' ) );
		add_filter( 'woocommerce_shipping_fields', array( $this, 'customize_shipping_fields' ) );

		// Script para recálculo por ciudad en checkout clásico
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_scripts' ) );

		// Guardar origen de la orden
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_source' ), 10, 2 );

		// Validación de carritos mixtos en checkout clásico
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_mixed_cart_checkout' ) );
	}

	/**
	 * Personaliza los campos de dirección de WooCommerce para Paraguay.
	 *
	 * @param array $fields
	 * @return array
	 */
	public function customize_address_fields( $fields ) {
		if ( isset( $fields['city'] ) ) {
			$fields['city']['label']       = __( 'Ciudad / Distrito', 'coco-cod' );
			$fields['city']['placeholder'] = __( 'Seleccione o escriba su ciudad', 'coco-cod' );
		}

		if ( isset( $fields['state'] ) ) {
			$fields['state']['label'] = __( 'Departamento', 'coco-cod' );
		}

		// En Paraguay los códigos postales no son obligatorios para entregas
		if ( isset( $fields['postcode'] ) ) {
			$fields['postcode']['required'] = false;
		}

		return $fields;
	}

	/**
	 * Asegura que el código postal de facturación no sea obligatorio.
	 */
	public function customize_billing_fields( $fields ) {
		if ( isset( $fields['billing_postcode'] ) ) {
			$fields['billing_postcode']['required'] = false;
		}
		return $fields;
	}

	/**
	 * Asegura que el código postal de envío no sea obligatorio.
	 */
	public function customize_shipping_fields( $fields ) {
		if ( isset( $fields['shipping_postcode'] ) ) {
			$fields['shipping_postcode']['required'] = false;
		}
		return $fields;
	}

	/**
	 * Encolar script en página de checkout clásico para forzar update_checkout al cambiar de ciudad.
	 */
	public function enqueue_checkout_scripts() {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		$script = "
		jQuery(document).ready(function($) {
			$(document).on('change', 'select#billing_city, input#billing_city', function() {
				$(document.body).trigger('update_checkout');
			});
		});
		";

		wp_add_inline_script( 'wc-checkout', $script );
	}

	/**
	 * Guardar metadatos en el pedido al crearse.
	 *
	 * @param \WC_Order $order
	 * @param array     $data
	 */
	public function save_order_source( $order, $data ) {
		if ( isset( $_POST['coco_cod_order_source'] ) ) {
			$order->update_meta_data( '_coco_cod_source', sanitize_text_field( $_POST['coco_cod_order_source'] ) );
		}

		if ( isset( $_POST['payment_method_choice'] ) ) {
			$choice = sanitize_text_field( $_POST['payment_method_choice'] );
			$order->update_meta_data( '_coco_cod_payment_type', $choice );

			if ( 'transfer' === $choice ) {
				$order->set_payment_method_title( __( 'Transferencia Bancaria / QR (Ueno)', 'coco-cod' ) );
				$order->add_order_note( __( 'Pedido con opción de Transferencia Bancaria / QR (Ueno). Enviar o verificar comprobante.', 'coco-cod' ) );
			} else {
				$order->set_payment_method_title( __( 'Pago Contra Entrega (Efectivo)', 'coco-cod' ) );
			}
		}
	}

	/**
	 * Valida que no se mezclen productos de Dropi y Stock Propio en pedidos COD del checkout tradicional.
	 */
	public function validate_mixed_cart_checkout() {
		if ( ! isset( $_POST['payment_method'] ) || 'cod' !== $_POST['payment_method'] ) {
			return;
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$has_dropi = false;
		$has_own   = false;
		$plugin    = Plugin::instance();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['data'] ) ) {
				continue;
			}
			$product = $cart_item['data'];
			if ( $plugin->is_dropi_product( $product ) ) {
				$has_dropi = true;
			} else {
				$has_own = true;
			}
		}

		if ( $has_dropi && $has_own ) {
			wc_add_notice(
				__( 'Por razones logísticas de contra entrega, no es posible combinar productos de Stock Propio con productos de Dropi en un mismo pedido. Por favor realiza dos pedidos por separado.', 'coco-cod' ),
				'error'
			);
		}
	}
}
