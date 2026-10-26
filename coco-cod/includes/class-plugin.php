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
	}

	/**
	 * Registrar hooks globales.
	 */
	private function init_hooks() {
		// Asegurar que Paraguay tenga todos sus departamentos en WooCommerce
		add_filter( 'woocommerce_states', array( $this, 'register_py_states' ) );

		// Enriquecer el selector de países/estados si es necesario
		add_filter( 'woocommerce_countries_allowed_country_states', array( $this, 'ensure_py_states' ) );
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
	 * Asegurar que todos los 17 departamentos + Capital estén en WooCommerce para PY.
	 *
	 * @param array $states
	 * @return array
	 */
	public function register_py_states( $states ) {
		$locations = $this->get_locations();
		if ( empty( $locations ) ) {
			return $states;
		}

		$py_states = array();
		foreach ( $locations as $code => $data ) {
			$py_states[ $code ] = $data['name'];
		}

		$states['PY'] = $py_states;
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
