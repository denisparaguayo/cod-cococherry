<?php
namespace Coco_COD;

defined( 'ABSPATH' ) || exit;

/**
 * Clase principal orquestadora del plugin Coco COD.
 */
class Plugin {

	/**
	 * Instancia Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Ajustes en caché.
	 *
	 * @var array
	 */
	private $settings = null;

	/**
	 * Datos geográficos de Paraguay.
	 *
	 * @var array|null
	 */
	private $locations = null;

	/**
	 * Submódulos.
	 */
	public $settings_module;
	public $shipping_module;
	public $checkout_module;
	public $frontend_module;
	public $ajax_module;
	public $health_module;
	public $updater_module;

	/**
	 * Obtener instancia singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor protegido.
	 */
	private function __construct() {
		$this->init_modules();
		$this->init_hooks();
	}

	/**
	 * Inicializar submódulos.
	 */
	private function init_modules() {
		$this->settings_module = new Settings();
		$this->shipping_module = new Shipping();
		$this->checkout_module = new Checkout();
		$this->frontend_module = new Frontend();
		$this->ajax_module     = new Ajax();
		$this->health_module   = new Health();
		$this->updater_module  = new Updater();
	}

	/**
	 * Registrar hooks globales.
	 */
	private function init_hooks() {
		// Asegurar que Paraguay tenga todos sus departamentos en WooCommerce
		add_filter( 'woocommerce_states', array( $this, 'register_py_states' ) );

		// Enriquecer el selector de países/estados si es necesario
		add_filter( 'woocommerce_countries_allowed_country_states', array( $this, 'ensure_py_states' ) );

		// Compatibilidad de sincronización con Dropi para pedidos de Paraguay
		add_filter( 'woocommerce_order_get_shipping_state', array( $this, 'filter_order_shipping_state' ), 20, 2 );
		add_filter( 'woocommerce_order_get_billing_state', array( $this, 'filter_order_billing_state' ), 20, 2 );
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'filter_rest_order_for_dropi' ), 20, 3 );
	}

	/**
	 * Obtener un ajuste específico del plugin.
	 *
	 * @param string $key Clave del ajuste.
	 * @param mixed  $default Valor por defecto.
	 * @return mixed
	 */
	public function get_setting( $key, $default = '' ) {
		if ( is_null( $this->settings ) ) {
			$this->settings = get_option( 'coco_cod_settings', array() );
		}

		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Refrescar caché de ajustes.
	 */
	public function reload_settings() {
		$this->settings = get_option( 'coco_cod_settings', array() );
	}

	/**
	 * Obtener los departamentos y ciudades desde el JSON local.
	 *
	 * @return array
	 */
	public function get_locations() {
		if ( is_null( $this->locations ) ) {
			$file = COCO_COD_PATH . 'data/py-locations.json';
			if ( file_exists( $file ) ) {
				$json = file_get_contents( $file );
				$this->locations = json_decode( $json, true );
			} else {
				$this->locations = array();
			}
		}
		return $this->locations;
	}

	/**
	 * Verificar si un producto corresponde a Dropi.
	 *
	 * @param \WC_Product|int $product
	 * @return bool
	 */
	public function is_dropi_product( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}

		// 1. Meta directo de Dropi
		$dropi_meta = $product->get_meta( '_dropi_product', true );
		if ( ! empty( $dropi_meta ) ) {
			return true;
		}

		// 2. Comprobar por clase de envío: si tiene 'stock-propio' NO es Dropi
		$shipping_class = $product->get_shipping_class();
		if ( 'stock-propio' === $shipping_class ) {
			return false;
		}

		// 3. Comprobar por SKU: si empieza por CC- es propio, de lo contrario se asume Dropi
		$sku = (string) $product->get_sku();
		if ( 0 === strpos( strtoupper( $sku ), 'CC-' ) ) {
			return false;
		}

		// Si no tiene clase stock-propio ni SKU CC-, es Dropi
		return true;
	}

	/**
	 * Verificar si un producto es Stock Propio.
	 *
	 * @param \WC_Product|int $product
	 * @return bool
	 */
	public function is_own_product( $product ) {
		return ! $this->is_dropi_product( $product );
	}

	/**
	 * Mapeo de departamentos de Paraguay a la nomenclatura exacta que exige Dropi.
	 *
	 * @param string $state Código o nombre del departamento.
	 * @return string Nombre reconocido por la API de Dropi.
	 */
	public function get_dropi_state_name( $state ) {
		$state_clean = trim( (string) $state );
		$map = array(
			'0'                => 'Asuncion',
			'ASU'              => 'Asuncion',
			'PY-ASU'           => 'Asuncion',
			'Capital'          => 'Asuncion',
			'Distrito Capital' => 'Asuncion',
			'Asunción'         => 'Asuncion',
			'Asuncion'         => 'Asuncion',
			'1'                => 'Concepcion',
			'PY-1'             => 'Concepcion',
			'Concepción'       => 'Concepcion',
			'Concepcion'       => 'Concepcion',
			'2'                => 'San Pedro',
			'PY-2'             => 'San Pedro',
			'3'                => 'Cordillera',
			'PY-3'             => 'Cordillera',
			'4'                => 'Guaira',
			'PY-4'             => 'Guaira',
			'Guairá'           => 'Guaira',
			'5'                => 'Caaguazu',
			'PY-5'             => 'Caaguazu',
			'Caaguazú'         => 'Caaguazu',
			'6'                => 'Caazapa',
			'PY-6'             => 'Caazapa',
			'Caazapá'          => 'Caazapa',
			'7'                => 'Itapua',
			'PY-7'             => 'Itapua',
			'Itapúa'           => 'Itapua',
			'8'                => 'Misiones',
			'PY-8'             => 'Misiones',
			'9'                => 'Paraguari',
			'PY-9'             => 'Paraguari',
			'Paraguarí'        => 'Paraguari',
			'10'               => 'Alto Paraná',
			'PY-10'            => 'Alto Paraná',
			'Alto Parana'      => 'Alto Paraná',
			'11'               => 'Central',
			'PY-11'            => 'Central',
			'12'               => 'Ñeembucú',
			'PY-12'            => 'Ñeembucú',
			'Neembucu'         => 'Ñeembucú',
			'13'               => 'Amambay',
			'PY-13'            => 'Amambay',
			'14'               => 'Canindeyu',
			'PY-14'            => 'Canindeyu',
			'Canindeyú'        => 'Canindeyu',
			'15'               => 'Presidente Hayes',
			'PY-15'            => 'Presidente Hayes',
			'16'               => 'Alto Paraguay',
			'PY-16'            => 'Alto Paraguay',
			'17'               => 'Boqueron',
			'PY-17'            => 'Boqueron',
			'Boquerón'         => 'Boqueron',
		);

		return isset( $map[ $state_clean ] ) ? $map[ $state_clean ] : $state;
	}

	/**
	 * Filtra el estado de envío del pedido para devolver la nomenclatura de Dropi en Paraguay.
	 */
	public function filter_order_shipping_state( $value, $order ) {
		if ( $order instanceof \WC_Order && 'PY' === $order->get_shipping_country() ) {
			return $this->get_dropi_state_name( $value );
		}
		return $value;
	}

	/**
	 * Filtra el estado de facturación del pedido para devolver la nomenclatura de Dropi en Paraguay.
	 */
	public function filter_order_billing_state( $value, $order ) {
		if ( $order instanceof \WC_Order && 'PY' === $order->get_billing_country() ) {
			return $this->get_dropi_state_name( $value );
		}
		return $value;
	}

	/**
	 * Filtra la respuesta de la REST API de WooCommerce cuando Dropi consulta el pedido.
	 */
	public function filter_rest_order_for_dropi( $response, $order, $request ) {
		if ( ! empty( $response->data['shipping']['country'] ) && 'PY' === $response->data['shipping']['country'] ) {
			$raw_state = $response->data['shipping']['state'] ?? '';
			$response->data['shipping']['state'] = $this->get_dropi_state_name( $raw_state );
		}
		if ( ! empty( $response->data['billing']['country'] ) && 'PY' === $response->data['billing']['country'] ) {
			$raw_state = $response->data['billing']['state'] ?? '';
			$response->data['billing']['state'] = $this->get_dropi_state_name( $raw_state );
		}
		return $response;
	}

	/**
	 * Normaliza el código de departamento para que coincida exactamente con las tarifas internas.
	 * Asunción / Capital es '0'. Departamentos son '1' a '17'.
	 *
	 * @param string $state
	 * @return string
	 */
	public function normalize_state_code( $state ) {
		$state = trim( (string) $state );
		$upper = strtoupper( $state );
		if ( 'ASU' === $upper || 'ASUNCION' === $upper || 'ASUNCIÓN' === $upper || 'CAPITAL' === $upper || '00' === $state || '0' === $state || 'PY-ASU' === $upper ) {
			return '0';
		}
		if ( 'CENTRAL' === $upper || '11' === $state || 'PY-11' === $upper ) {
			return '11';
		}
		if ( preg_match( '/^0([1-9])$/', $state, $matches ) ) {
			return $matches[1];
		}
		return $state;
	}

	/**
	 * Asegurar que todos los departamentos estén en WooCommerce para PY.
	 *
	 * @param array $states
	 * @return array
	 */
	public function register_py_states( $states ) {
		$locations = $this->get_locations();
		if ( empty( $locations ) ) {
			return $states;
		}

		if ( ! isset( $states['PY'] ) || ! is_array( $states['PY'] ) ) {
			$states['PY'] = array();
		}

		foreach ( $locations as $code => $data ) {
			$clean_code = $this->normalize_state_code( $code );
			$states['PY'][ $clean_code ] = $data['name'];
			// Mapear también el nombre Dropi para que WooCommerce lo reconozca en cualquier desplegable
			$dropi_name = $this->get_dropi_state_name( $clean_code );
			$states['PY'][ $dropi_name ] = $data['name'];
		}

		return $states;
	}

	/**
	 * Asegurar que los estados estén listados para PY.
	 *
	 * @param array $states
	 * @return array
	 */
	public function ensure_py_states( $states ) {
		if ( ! isset( $states['PY'] ) ) {
			$states['PY'] = array();
		}
		return $states;
	}

	/**
	 * Comprueba si el modo activo está permitido según test_mode.
	 *
	 * @return bool
	 */
	public function is_active_for_current_user() {
		if ( 'yes' !== $this->get_setting( 'enabled', 'yes' ) ) {
			return false;
		}

		if ( 'yes' === $this->get_setting( 'test_mode', 'no' ) ) {
			// Solo visible para administradores o con cookie especial ?coco_test=1
			if ( current_user_can( 'manage_woocommerce' ) ) {
				return true;
			}
			if ( isset( $_GET['coco_test'] ) && '1' === $_GET['coco_test'] ) {
				return true;
			}
			return false;
		}

		return true;
	}
}
