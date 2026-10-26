<?php
namespace Coco_COD;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoints AJAX seguros para cálculo de envío y creación de pedidos.
 */
class Ajax {

	public function __construct() {
		// Cálculo de flete
		add_action( 'wp_ajax_coco_cod_shipping', array( $this, 'ajax_calculate_shipping' ) );
		add_action( 'wp_ajax_nopriv_coco_cod_shipping', array( $this, 'ajax_calculate_shipping' ) );

		// Creación de orden
		add_action( 'wp_ajax_coco_cod_order', array( $this, 'ajax_process_order' ) );
		add_action( 'wp_ajax_nopriv_coco_cod_order', array( $this, 'ajax_process_order' ) );

		// Nonce fresco para caché
		add_action( 'wp_ajax_coco_cod_nonce', array( $this, 'ajax_get_fresh_nonce' ) );
		add_action( 'wp_ajax_nopriv_coco_cod_nonce', array( $this, 'ajax_get_fresh_nonce' ) );
	}

	/**
	 * Endpoint ligero para cálculo en vivo de envío en el Paso 1.
	 */
	public function ajax_calculate_shipping() {
		check_ajax_referer( 'coco_cod_nonce', 'security' );

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$qty          = isset( $_POST['qty'] ) ? max( 1, absint( $_POST['qty'] ) ) : 1;
		$state        = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$city         = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';

		$target_id = $variation_id ? $variation_id : $product_id;
		$product   = wc_get_product( $target_id );

		if ( ! $product instanceof \WC_Product ) {
			wp_send_json_error( array( 'message' => __( 'Producto no válido.', 'coco-cod' ) ) );
		}

		$plugin        = Plugin::instance();
		$has_own_stock = $plugin->is_own_product( $product );
		$shipping_cost = $plugin->shipping_module->get_shipping_cost( $state, $city, $has_own_stock );

		$price    = (float) $product->get_price();
		$subtotal = $price * $qty;
		$total    = $subtotal + $shipping_cost;

		wp_send_json_success( array(
			'shipping_cost'      => $shipping_cost,
			'formatted_shipping' => 0 === $shipping_cost ? __( 'Gratis', 'coco-cod' ) : wc_price( $shipping_cost ),
			'subtotal'           => $subtotal,
			'formatted_subtotal' => wc_price( $subtotal ),
			'total'              => $total,
			'formatted_total'    => wc_price( $total ),
		) );
	}

	/**
	 * Endpoint principal para procesar y crear la orden vía WC_Checkout::process_checkout.
	 */
	public function ajax_process_order() {
		check_ajax_referer( 'coco_cod_nonce', 'security' );

		// 1. Anti-spam: Honeypot (campo trampa que los humanos dejan vacío)
		if ( ! empty( $_POST['coco_hp_code'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Error de validación anti-spam.', 'coco-cod' ) ) );
		}

		// 2. Anti-spam: Time-trap (mínimo 2.5 segundos para llenar 3 pasos)
		$rendered_at = isset( $_POST['form_rendered_at'] ) ? absint( $_POST['form_rendered_at'] ) : 0;
		if ( $rendered_at > 0 && ( time() - $rendered_at ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Envío demasiado rápido. Por favor intenta de nuevo.', 'coco-cod' ) ) );
		}

		// 3. Idempotencia: evitar pedidos duplicados por doble clic
		$idempotency_key = isset( $_POST['idempotency_key'] ) ? sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ) ) : '';
		if ( ! empty( $idempotency_key ) ) {
			$transient_name = 'coco_cod_lock_' . md5( $idempotency_key );
			if ( get_transient( $transient_name ) ) {
				wp_send_json_error( array( 'message' => __( 'Este pedido ya está siendo procesado.', 'coco-cod' ) ) );
			}
			set_transient( $transient_name, '1', 10 * MINUTE_IN_SECONDS );
		}

		// 4. Sanitización de datos del formulario
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$state      = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$city       = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$address_1  = isset( $_POST['address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['address_1'] ) ) : '';
		$address_2  = isset( $_POST['address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['address_2'] ) ) : '';

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$qty          = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;

		// 5. Validaciones de campos obligatorios
		if ( mb_strlen( $first_name ) < 2 || mb_strlen( $last_name ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Por favor ingresa tu nombre y apellido completos.', 'coco-cod' ) ) );
		}

		if ( empty( $phone ) || strlen( preg_replace( '/\D/', '', $phone ) ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Por favor ingresa un número de celular válido.', 'coco-cod' ) ) );
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Por favor ingresa un correo electrónico real y válido.', 'coco-cod' ) ) );
		}

		if ( empty( $state ) || empty( $city ) || mb_strlen( $address_1 ) < 4 ) {
			wp_send_json_error( array( 'message' => __( 'Por favor completa tu departamento, ciudad y dirección exacta.', 'coco-cod' ) ) );
		}

		// 6. Validación de producto y stock
		$target_id = $variation_id ? $variation_id : $product_id;
		$product   = wc_get_product( $target_id );

		if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'El producto seleccionado no está disponible en este momento.', 'coco-cod' ) ) );
		}

		// Validar que Dropi no se envíe a Alto Paraguay (16)
		$plugin = Plugin::instance();
		if ( '16' === $state && $plugin->is_dropi_product( $product ) ) {
			wp_send_json_error( array( 'message' => __( 'Dropi no cuenta con cobertura de entrega en el departamento de Alto Paraguay.', 'coco-cod' ) ) );
		}

		// 7. Preparación de sesión y carrito en WooCommerce
		if ( null === WC()->session ) {
			WC()->initialize_session();
		}
		if ( null === WC()->customer ) {
			WC()->customer = new \WC_Customer( get_current_user_id(), true );
		}
		if ( null === WC()->cart ) {
			WC()->cart = new \WC_Cart();
		}

		// Vaciar carrito previo para asegurar flujo 1-clic directo
		WC()->cart->empty_cart();

		// Extraer atributos de variación si existen
		$variation_attrs = array();
		if ( ! empty( $_POST['variation_attrs'] ) && is_array( $_POST['variation_attrs'] ) ) {
			foreach ( $_POST['variation_attrs'] as $key => $val ) {
				$variation_attrs[ sanitize_text_field( $key ) ] = sanitize_text_field( $val );
			}
		}

		// Añadir al carrito
		$cart_item_key = WC()->cart->add_to_cart( $product_id, $qty, $variation_id, $variation_attrs );
		if ( ! $cart_item_key ) {
			wp_send_json_error( array( 'message' => __( 'No se pudo añadir el producto al carrito. Por favor verifica el stock.', 'coco-cod' ) ) );
		}

		// Asignar datos del cliente
		WC()->customer->set_billing_first_name( $first_name );
		WC()->customer->set_billing_last_name( $last_name );
		WC()->customer->set_billing_phone( $phone );
		WC()->customer->set_billing_email( $email );
		WC()->customer->set_billing_country( 'PY' );
		WC()->customer->set_billing_state( $state );
		WC()->customer->set_billing_city( $city );
		WC()->customer->set_billing_address_1( $address_1 );
		WC()->customer->set_billing_address_2( $address_2 );

		WC()->customer->set_shipping_first_name( $first_name );
		WC()->customer->set_shipping_last_name( $last_name );
		WC()->customer->set_shipping_phone( $phone );
		WC()->customer->set_shipping_country( 'PY' );
		WC()->customer->set_shipping_state( $state );
		WC()->customer->set_shipping_city( $city );
		WC()->customer->set_shipping_address_1( $address_1 );
		WC()->customer->set_shipping_address_2( $address_2 );

		// Recalcular envíos y totales
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		// Asignar primer método de envío disponible
		$packages = WC()->shipping()->get_packages();
		if ( ! empty( $packages ) ) {
			foreach ( $packages as $i => $package ) {
				if ( ! empty( $package['rates'] ) ) {
					$rate_keys = array_keys( $package['rates'] );
					$chosen_method = reset( $rate_keys );
					WC()->session->set( 'chosen_shipping_methods', array( $chosen_method ) );
					$_POST['shipping_method'] = array( $chosen_method );
					break;
				}
			}
		}

		// 8. Preparar $_POST nativo para WC_Checkout
		$_POST['payment_method']              = 'cod';
		$_POST['billing_first_name']          = $first_name;
		$_POST['billing_last_name']           = $last_name;
		$_POST['billing_phone']               = $phone;
		$_POST['billing_email']               = $email;
		$_POST['billing_country']             = 'PY';
		$_POST['billing_state']               = $state;
		$_POST['billing_city']                = $city;
		$_POST['billing_address_1']           = $address_1;
		$_POST['billing_address_2']           = $address_2;
		$_POST['ship_to_different_address']   = 0;
		$_POST['coco_cod_order_source']       = 'modal';
		$_POST['woocommerce-process-checkout-nonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
		$_POST['_wpnonce']                    = $_POST['woocommerce-process-checkout-nonce'];

		// 9. Ejecutar el checkout nativo de WooCommerce
		// En AJAX, process_checkout() llamará internamente a wp_send_json( array( 'result' => 'success', 'redirect' => ... ) ) y saldrá
		WC()->checkout()->process_checkout();

		// Si por alguna razón la ejecución llega aquí sin errores:
		$notices = wc_print_notices( true );
		if ( ! empty( $notices ) ) {
			wp_send_json_error( array( 'message' => wp_strip_all_tags( $notices ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'No se pudo procesar la orden. Por favor intenta nuevamente.', 'coco-cod' ) ) );
	}

	/**
	 * Entrega de nonces frescos para páginas cacheadas.
	 */
	public function ajax_get_fresh_nonce() {
		wp_send_json_success( array(
			'nonce' => wp_create_nonce( 'coco_cod_nonce' ),
		) );
	}
}
