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

		$price          = (float) $product->get_price();
		$subtotal       = $price * $qty;
		$total          = $subtotal + $shipping_cost;
		$currency_sym   = get_woocommerce_currency_symbol();

		wp_send_json_success( array(
			'shipping_cost'      => $shipping_cost,
			'formatted_shipping' => 0 === $shipping_cost ? __( 'Gratis', 'coco-cod' ) : wc_price( $shipping_cost ),
			'subtotal'           => $subtotal,
			'formatted_subtotal' => wc_price( $subtotal ),
			'total'              => $total,
			'formatted_total'    => wc_price( $total ),
			'plain_total'        => number_format( $total, 0, ',', '.' ) . ' ' . $currency_sym,
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

		$plugin = Plugin::instance();
		$state  = $plugin->normalize_state_code( $state );

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

		if ( '' === $state || empty( $city ) || mb_strlen( $address_1 ) < 4 ) {
			wp_send_json_error( array( 'message' => __( 'Por favor completa tu departamento, ciudad y dirección exacta.', 'coco-cod' ) ) );
		}

		// 6. Validación de producto y stock
		$target_id = $variation_id ? $variation_id : $product_id;
		$product   = wc_get_product( $target_id );

		if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'El producto seleccionado no está disponible en este momento.', 'coco-cod' ) ) );
		}

		// Validar que Dropi no se envíe a Alto Paraguay (16)
		if ( '16' === $state && $plugin->is_dropi_product( $product ) ) {
			wp_send_json_error( array( 'message' => __( 'Lo sentimos, este producto no cuenta con cobertura de entrega en el departamento de Alto Paraguay.', 'coco-cod' ) ) );
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
		$postcode = '0000';
		WC()->customer->set_billing_first_name( $first_name );
		WC()->customer->set_billing_last_name( $last_name );
		WC()->customer->set_billing_phone( $phone );
		WC()->customer->set_billing_email( $email );
		WC()->customer->set_billing_country( 'PY' );
		WC()->customer->set_billing_state( $state );
		WC()->customer->set_billing_city( $city );
		WC()->customer->set_billing_postcode( $postcode );
		WC()->customer->set_billing_address_1( $address_1 );
		WC()->customer->set_billing_address_2( $address_2 );

		WC()->customer->set_shipping_first_name( $first_name );
		WC()->customer->set_shipping_last_name( $last_name );
		WC()->customer->set_shipping_phone( $phone );
		WC()->customer->set_shipping_country( 'PY' );
		WC()->customer->set_shipping_state( $state );
		WC()->customer->set_shipping_city( $city );
		WC()->customer->set_shipping_postcode( $postcode );
		WC()->customer->set_shipping_address_1( $address_1 );
		WC()->customer->set_shipping_address_2( $address_2 );
		WC()->customer->save();

		// Recalcular envíos y totales
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		// Asignar primer método de envío disponible
		$packages = WC()->shipping()->get_packages();
		$chosen_method = 'coco_cod_rate';
		if ( ! empty( $packages ) ) {
			foreach ( $packages as $i => $package ) {
				if ( ! empty( $package['rates'] ) ) {
					$rate_keys     = array_keys( $package['rates'] );
					$chosen_method = reset( $rate_keys );
					break;
				}
			}
		}

		WC()->session->set( 'chosen_shipping_methods', array( $chosen_method ) );

		// 8. Preparar $_POST y $_REQUEST completos para WC_Checkout
		$wc_nonce = wp_create_nonce( 'woocommerce-process_checkout' );

		// Determinar método de pago (transfer vs cod)
		$payment_choice = isset( $_POST['payment_method_choice'] ) ? sanitize_text_field( $_POST['payment_method_choice'] ) : 'cod';
		if ( $plugin->is_dropi_product( $product ) ) {
			$payment_choice = 'cod';
		}
		$wc_payment_method = ( 'transfer' === $payment_choice ) ? 'bacs' : 'cod';

		$_POST['payment_method']                    = $wc_payment_method;
		$_POST['payment_method_choice']             = $payment_choice;
		$_POST['billing_first_name']                = $first_name;
		$_POST['billing_last_name']                 = $last_name;
		$_POST['billing_phone']                     = $phone;
		$_POST['billing_email']                     = $email;
		$_POST['billing_country']                   = 'PY';
		$_POST['billing_state']                     = $state;
		$_POST['billing_city']                      = $city;
		$_POST['billing_postcode']                  = $postcode;
		$_POST['billing_address_1']                 = $address_1;
		$_POST['billing_address_2']                 = $address_2;

		$_POST['shipping_first_name']               = $first_name;
		$_POST['shipping_last_name']                = $last_name;
		$_POST['shipping_phone']                    = $phone;
		$_POST['shipping_country']                  = 'PY';
		$_POST['shipping_state']                    = $state;
		$_POST['shipping_city']                     = $city;
		$_POST['shipping_postcode']                 = $postcode;
		$_POST['shipping_address_1']                = $address_1;
		$_POST['shipping_address_2']                = $address_2;

		$_POST['ship_to_different_address']         = 0;
		$_POST['shipping_method']                   = array( $chosen_method );
		$_POST['coco_cod_order_source']             = 'modal';
		$_POST['woocommerce-process-checkout-nonce'] = $wc_nonce;
		$_POST['_wpnonce']                          = $wc_nonce;
		$_POST['terms']                             = 1;
		$_POST['terms-field']                       = 1;

		// Evitar que WooCommerce exija código postal durante el checkout modal
		add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
			if ( isset( $fields['billing']['billing_postcode'] ) ) {
				$fields['billing']['billing_postcode']['required'] = false;
			}
			if ( isset( $fields['shipping']['shipping_postcode'] ) ) {
				$fields['shipping']['shipping_postcode']['required'] = false;
			}
			return $fields;
		}, 9999 );

		// IMPORTANTE: WC_Checkout lee los nonces de $_REQUEST, que en PHP no se actualiza solo con $_POST
		foreach ( $_POST as $key => $val ) {
			$_REQUEST[ $key ] = $val;
		}

		// Asegurar que la pasarela elegida (COD o BACS) esté activa y disponible durante este checkout
		add_filter( 'woocommerce_available_payment_gateways', function ( $gateways ) use ( $wc_payment_method ) {
			if ( 'bacs' === $wc_payment_method ) {
				if ( ! isset( $gateways['bacs'] ) && class_exists( '\WC_Gateway_BACS' ) ) {
					$gateways['bacs'] = new \WC_Gateway_BACS();
				}
				if ( isset( $gateways['bacs'] ) ) {
					$gateways['bacs']->enabled = 'yes';
					$gateways['bacs']->title   = __( 'Transferencia Bancaria / QR (Ueno)', 'coco-cod' );
				}
			} else {
				if ( ! isset( $gateways['cod'] ) && class_exists( '\WC_Gateway_COD' ) ) {
					$gateways['cod'] = new \WC_Gateway_COD();
				}
				if ( isset( $gateways['cod'] ) ) {
					$gateways['cod']->enabled = 'yes';
				}
			}
			return $gateways;
		}, 999 );

		// 9. Ejecutar el checkout nativo de WooCommerce
		// En AJAX, process_checkout() llamará internamente a wp_send_json( array( 'result' => 'success', 'redirect' => ... ) ) y saldrá
		WC()->checkout()->process_checkout();

		// Si por alguna razón la ejecución llega aquí con errores:
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
