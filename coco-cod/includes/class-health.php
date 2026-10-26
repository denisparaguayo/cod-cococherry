<?php
namespace Coco_COD;

defined( 'ABSPATH' ) || exit;

/**
 * Herramientas de diagnóstico, comprobación de compatibilidad y sincronización de clases.
 */
class Health {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'process_sync_tool' ) );
	}

	public function process_sync_tool() {
		if ( ! isset( $_POST['coco_cod_sync_classes'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'coco_cod_sync_action', 'coco_cod_sync_nonce' ) ) {
			wp_die( 'Acceso inválido.' );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Permisos insuficientes.' );
		}

		$is_dry_run = isset( $_POST['is_dry_run'] );

		// Obtener o crear término de clase de envío 'stock-propio'
		$term = get_term_by( 'slug', 'stock-propio', 'product_shipping_class' );
		if ( ! $term && ! $is_dry_run ) {
			$new_term = wp_insert_term( 'Stock Propio', 'product_shipping_class', array(
				'slug'        => 'stock-propio',
				'description' => 'Productos con logística y delivery propio',
			) );
			if ( ! is_wp_error( $new_term ) ) {
				$term_id = $new_term['term_id'];
			}
		} elseif ( $term ) {
			$term_id = $term->term_id;
		} else {
			$term_id = 0;
		}

		// Buscar todos los productos con SKU CC-
		$args = array(
			'limit'  => -1,
			'status' => 'any',
			'return' => 'ids',
		);
		$all_products = wc_get_products( $args );

		$affected = 0;
		foreach ( $all_products as $p_id ) {
			$prod = wc_get_product( $p_id );
			if ( ! $prod ) {
				continue;
			}

			$sku = (string) $prod->get_sku();
			if ( 0 === strpos( strtoupper( $sku ), 'CC-' ) ) {
				if ( 'stock-propio' !== $prod->get_shipping_class() ) {
					$affected++;
					if ( ! $is_dry_run && $term_id ) {
						$prod->set_shipping_class_id( $term_id );
						$prod->save();
					}
				}
			}
		}

		$redirect_url = add_query_arg( array(
			'page'     => 'coco-cod',
			'tab'      => 'health',
			'synced'   => $is_dry_run ? 'dry_run' : 'applied',
			'affected' => $affected,
		), admin_url( 'admin.php' ) );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function render_health_tab() {
		// 1. Comprobar pasarela COD
		$gateways = WC()->payment_gateways()->payment_gateways();
		$cod_enabled = isset( $gateways['cod'] ) && 'yes' === $gateways['cod']->enabled;

		// 2. Comprobar página de checkout (Clásico vs Bloques)
		$checkout_page_id = wc_get_page_id( 'checkout' );
		$is_classic = false;
		$checkout_content = '';
		if ( $checkout_page_id > 0 ) {
			$post = get_post( $checkout_page_id );
			if ( $post ) {
				$checkout_content = $post->post_content;
				$is_classic = has_shortcode( $checkout_content, 'woocommerce_checkout' );
			}
		}

		// 3. Contar productos por tipo
		$all_products = wc_get_products( array( 'limit' => -1, 'return' => 'ids' ) );
		$own_count   = 0;
		$dropi_count = 0;
		$missing_class = 0;
		$plugin = Plugin::instance();

		foreach ( $all_products as $p_id ) {
			$prod = wc_get_product( $p_id );
			if ( ! $prod ) {
				continue;
			}
			$sku = (string) $prod->get_sku();
			$has_cc = ( 0 === strpos( strtoupper( $sku ), 'CC-' ) );

			if ( $plugin->is_own_product( $prod ) ) {
				$own_count++;
			} else {
				$dropi_count++;
			}

			if ( $has_cc && 'stock-propio' !== $prod->get_shipping_class() ) {
				$missing_class++;
			}
		}
		?>
		<div style="max-width:850px; margin-top:20px;">
			<?php if ( isset( $_GET['synced'] ) ) : ?>
				<?php $affected = isset( $_GET['affected'] ) ? absint( $_GET['affected'] ) : 0; ?>
				<div class="notice notice-success is-dismissible">
					<?php if ( 'dry_run' === $_GET['synced'] ) : ?>
						<p><?php printf( esc_html__( 'Simulación completada: se encontraron %d productos con SKU CC- que necesitan la clase "stock-propio". No se hicieron cambios.', 'coco-cod' ), $affected ); ?></p>
					<?php else : ?>
						<p><?php printf( esc_html__( 'Sincronización aplicada exitosamente a %d productos.', 'coco-cod' ), $affected ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Diagnóstico del Sistema y Compatibilidad', 'coco-cod' ); ?></h2>

			<table class="widefat striped" style="margin-bottom:25px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Elemento', 'coco-cod' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'coco-cod' ); ?></th>
						<th><?php esc_html_e( 'Detalles', 'coco-cod' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Pago Contra Entrega (COD)', 'coco-cod' ); ?></strong></td>
						<td>
							<?php if ( $cod_enabled ) : ?>
								<span style="color:#16a34a; font-weight:bold;">✔ Activo</span>
							<?php else : ?>
								<span style="color:#dc2626; font-weight:bold;">✖ Desactivado</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $cod_enabled ) : ?>
								<?php esc_html_e( 'La pasarela nativa de Pago Contra Entrega está lista para procesar órdenes.', 'coco-cod' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Atención: debes activar "Pago contra entrega" en WooCommerce > Ajustes > Pagos.', 'coco-cod' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Tipo de Checkout', 'coco-cod' ); ?></strong></td>
						<td>
							<?php if ( $is_classic ) : ?>
								<span style="color:#16a34a; font-weight:bold;">✔ Checkout Clásico ([woocommerce_checkout])</span>
							<?php else : ?>
								<span style="color:#eab308; font-weight:bold;">⚠ Bloques Gutenberg detectados</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $is_classic ) : ?>
								<?php esc_html_e( '100% compatible con todos los hooks y recálculos por ciudad.', 'coco-cod' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Recomendado: edita tu página de Checkout y cambia los bloques por el shortcode [woocommerce_checkout] para garantizar compatibilidad con los filtros de ciudad.', 'coco-cod' ); ?>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Productos Detectados', 'coco-cod' ); ?></strong></td>
						<td>
							<span style="color:#2563eb; font-weight:bold;"><?php echo esc_html( count( $all_products ) ); ?> productos</span>
						</td>
						<td>
							<?php printf( esc_html__( 'Propios: %d | Dropi: %d | Faltan clase stock-propio: %d', 'coco-cod' ), $own_count, $dropi_count, $missing_class ); ?>
						</td>
					</tr>
				</tbody>
			</table>

			<hr>

			<h2><?php esc_html_e( 'Herramienta: Sincronizar Clases de Envío con SKU CC-', 'coco-cod' ); ?></h2>
			<p><?php esc_html_e( 'Esta herramienta busca todos los productos cuyo SKU empiece por "CC-" y les asigna automáticamente la clase de envío "Stock Propio" (slug: stock-propio).', 'coco-cod' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'coco_cod_sync_action', 'coco_cod_sync_nonce' ); ?>
				<input type="hidden" name="coco_cod_sync_classes" value="1">

				<p>
					<button type="submit" name="is_dry_run" value="1" class="button">
						<?php esc_html_e( 'Simular (Vista previa sin modificar)', 'coco-cod' ); ?>
					</button>
					<button type="submit" class="button button-primary" onclick="return confirm('¿Confirmas que deseas aplicar la clase Stock Propio a los productos con SKU CC-?');">
						<?php esc_html_e( 'Aplicar Sincronización', 'coco-cod' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}
}
