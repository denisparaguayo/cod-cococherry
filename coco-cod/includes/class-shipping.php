<?php
namespace Coco_COD;

defined( 'ABSPATH' ) || exit;

/**
 * Gestión del cálculo y filtrado de tarifas de envío por ciudad y zona.
 */
class Shipping {

	public function __construct() {
		add_filter( 'woocommerce_package_rates', array( $this, 'filter_package_rates' ), 100, 2 );
	}

	/**
	 * Determina si el paquete o carrito contiene productos con Stock Propio.
	 *
	 * @param array $package
	 * @return bool
	 */
	public function package_has_own_stock( $package ) {
		if ( empty( $package['contents'] ) ) {
			return false;
		}

		$plugin = Plugin::instance();

		foreach ( $package['contents'] as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof \WC_Product ) {
				continue;
			}
			$product = $item['data'];
			if ( $plugin->is_own_product( $product ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Calcula el costo de envío exacto según el departamento, ciudad y si contiene stock propio.
	 *
	 * @param string $state Código de departamento (ej. '11', 'ASU', '07').
	 * @param string $city Nombre de la ciudad (ej. 'Villeta', 'Encarnación').
	 * @param bool   $has_own_stock Indica si hay productos propios en el pedido.
	 * @return int Costo en Guaraníes.
	 */
	public function get_shipping_cost( $state, $city, $has_own_stock ) {
		// Si es solo Dropi, el costo es Gs 0
		if ( ! $has_own_stock ) {
			return 0;
		}

		$plugin    = Plugin::instance();
		$state     = $plugin->normalize_state_code( $state );
		$city_trim = mb_strtolower( trim( (string) $city ) );

		// 1. Verificar si la ciudad está en la lista de tarifas personalizadas
		$city_rates = $plugin->get_setting( 'city_rates', array() );
		if ( ! empty( $city_rates ) && is_array( $city_rates ) ) {
			foreach ( $city_rates as $rule ) {
				if ( ! empty( $rule['city'] ) && mb_strtolower( trim( $rule['city'] ) ) === $city_trim ) {
					$rule_state = ! empty( $rule['state'] ) ? $plugin->normalize_state_code( $rule['state'] ) : '';
					// Si se definió un estado, comprobar que coincida (o aceptar si coincide ciudad)
					if ( empty( $rule_state ) || $rule_state === $state ) {
						return absint( $rule['cost'] );
					}
				}
			}
		}

		// 2. Si no es ciudad especial, evaluar zona general
		// Asunción ('0') y Central ('11') -> Zona Capital y Central
		if ( '0' === $state || '11' === $state ) {
			return absint( $plugin->get_setting( 'shipping_central', 35000 ) );
		}

		// 3. Interior del país ('1' a '17' excepto '11')
		return absint( $plugin->get_setting( 'shipping_interior', 45000 ) );
	}

	/**
	 * Modifica las tarifas de envío de WooCommerce en tiempo real.
	 *
	 * @param array $rates Métodos de envío disponibles.
	 * @param array $package Información del paquete de envío.
	 * @return array
	 */
	public function filter_package_rates( $rates, $package ) {
		if ( empty( $rates ) || ! is_array( $rates ) ) {
			return $rates;
		}

		$state = isset( $package['destination']['state'] ) ? (string) $package['destination']['state'] : '';
		$city  = isset( $package['destination']['city'] ) ? (string) $package['destination']['city'] : '';

		$has_own_stock = $this->package_has_own_stock( $package );
		$target_cost   = $this->get_shipping_cost( $state, $city, $has_own_stock );

		foreach ( $rates as $rate_id => $rate ) {
			$rates[ $rate_id ]->cost  = $target_cost;
			$rates[ $rate_id ]->taxes = array();

			// Etiqueta clara según destino
			if ( 0 === $target_cost ) {
				$rates[ $rate_id ]->label = __( 'Envío Dropi (Gratis)', 'coco-cod' );
			} elseif ( ! empty( $city ) ) {
				$rates[ $rate_id ]->label = sprintf( __( 'Envío a %s', 'coco-cod' ), $city );
			}
		}

		return $rates;
	}
}
