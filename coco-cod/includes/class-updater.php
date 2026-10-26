<?php
namespace Coco_COD;

defined( 'ABSPATH' ) || exit;

/**
 * Gestor de actualizaciones automáticas y manuales directamente desde GitHub.
 */
class Updater {

	const GITHUB_REPO   = 'denisparaguayo/cod-cococherry';
	const GITHUB_BRANCH = 'main';
	const RAW_FILE_URL  = 'https://raw.githubusercontent.com/denisparaguayo/cod-cococherry/main/coco-cod/coco-cod.php';
	const ZIP_URL       = 'https://github.com/denisparaguayo/cod-cococherry/archive/refs/heads/main.zip';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'process_manual_update' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_wp_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_update_dir' ), 10, 4 );
	}

	/**
	 * Obtener la versión remota disponible en GitHub.
	 *
	 * @param bool $force Forzar consulta sin usar caché.
	 * @return string|false
	 */
	public function get_remote_version( $force = false ) {
		$cached = get_transient( 'coco_cod_github_version' );
		if ( false !== $cached && ! $force ) {
			return $cached;
		}

		$response = wp_remote_get( self::RAW_FILE_URL, array(
			'timeout'    => 15,
			'user-agent' => 'Coco-COD-Updater/' . COCO_COD_VERSION . '; ' . home_url(),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( preg_match( '/Version:\s*([0-9\.]+)/i', $body, $matches ) ) {
			$version = trim( $matches[1] );
			set_transient( 'coco_cod_github_version', $version, 2 * HOUR_IN_SECONDS );
			return $version;
		}

		return false;
	}

	/**
	 * Procesa la acción manual de comprobación y actualización directa.
	 */
	public function process_manual_update() {
		if ( ! isset( $_POST['coco_cod_github_action'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'coco_cod_github_update_action', 'coco_cod_github_nonce' ) ) {
			wp_die( esc_html__( 'Acceso de seguridad inválido.', 'coco-cod' ) );
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para actualizar plugins.', 'coco-cod' ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['coco_cod_github_action'] ) );

		if ( 'check' === $action ) {
			$this->get_remote_version( true );
			wp_safe_redirect( add_query_arg( array(
				'page' => 'coco-cod',
				'tab'  => 'updates',
				'checked' => '1',
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'update' === $action || 'force' === $action ) {
			$result = $this->perform_update();

			if ( is_wp_error( $result ) ) {
				$msg = urlencode( $result->get_error_message() );
				wp_safe_redirect( add_query_arg( array(
					'page'   => 'coco-cod',
					'tab'    => 'updates',
					'error'  => $msg,
				), admin_url( 'admin.php' ) ) );
				exit;
			}

			// Limpiar cachés de versión
			delete_transient( 'coco_cod_github_version' );
			delete_site_transient( 'update_plugins' );

			wp_safe_redirect( add_query_arg( array(
				'page'    => 'coco-cod',
				'tab'     => 'updates',
				'updated' => '1',
				'new_ver' => $result,
			), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/**
	 * Descarga el código desde GitHub y reemplaza los archivos del plugin.
	 *
	 * @return string|\WP_Error Versión instalada o WP_Error.
	 */
	public function perform_update() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		// Inicializar sistema de archivos WP
		\WP_Filesystem();
		global $wp_filesystem;

		if ( ! is_object( $wp_filesystem ) ) {
			return new \WP_Error( 'filesystem_error', __( 'No se pudo inicializar el sistema de archivos de WordPress.', 'coco-cod' ) );
		}

		// 1. Descargar archivo ZIP desde GitHub
		$temp_zip = download_url( self::ZIP_URL, 300 );
		if ( is_wp_error( $temp_zip ) ) {
			return new \WP_Error( 'download_failed', sprintf( __( 'Error al descargar el archivo desde GitHub: %s', 'coco-cod' ), $temp_zip->get_error_message() ) );
		}

		// 2. Crear directorio temporal para descomprimir
		$temp_dir = get_temp_dir() . 'coco_cod_update_' . time() . '/';
		$unzipped = unzip_file( $temp_zip, $temp_dir );
		@unlink( $temp_zip );

		if ( is_wp_error( $unzipped ) ) {
			return new \WP_Error( 'unzip_failed', sprintf( __( 'Error al descomprimir la actualización: %s', 'coco-cod' ), $unzipped->get_error_message() ) );
		}

		// 3. Localizar la carpeta coco-cod dentro del ZIP descomprimido
		// En GitHub suele descomprimirse como: cod-cococherry-main/coco-cod/
		$source_dir = '';
		if ( is_dir( $temp_dir . 'cod-cococherry-main/coco-cod' ) ) {
			$source_dir = $temp_dir . 'cod-cococherry-main/coco-cod';
		} else {
			// Búsqueda recursiva de coco-cod.php
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $temp_dir, \RecursiveDirectoryIterator::SKIP_DOTS ) );
			foreach ( $iterator as $item ) {
				if ( 'coco-cod.php' === $item->getFilename() ) {
					$source_dir = dirname( $item->getPathname() );
					break;
				}
			}
		}

		if ( empty( $source_dir ) || ! is_dir( $source_dir ) ) {
			$wp_filesystem->delete( $temp_dir, true );
			return new \WP_Error( 'invalid_structure', __( 'No se encontró la carpeta del plugin (coco-cod) dentro del repositorio descargado.', 'coco-cod' ) );
		}

		// 4. Copiar los archivos a la carpeta del plugin activo
		$destination = rtrim( COCO_COD_PATH, '/\\' );
		$copy_result = copy_dir( $source_dir, $destination );

		// 5. Limpiar directorio temporal
		$wp_filesystem->delete( $temp_dir, true );

		if ( is_wp_error( $copy_result ) ) {
			return new \WP_Error( 'copy_failed', sprintf( __( 'Error al copiar los nuevos archivos del plugin: %s', 'coco-cod' ), $copy_result->get_error_message() ) );
		}

		// 6. Leer la nueva versión desde el archivo recién copiado
		$new_ver = '1.0.0';
		$main_file = $destination . '/coco-cod.php';
		if ( file_exists( $main_file ) ) {
			$content = file_get_contents( $main_file );
			if ( preg_match( '/Version:\s*([0-9\.]+)/i', $content, $m ) ) {
				$new_ver = $m[1];
			}
		}

		return $new_ver;
	}

	/**
	 * Integración con las comprobaciones nativas de WordPress en wp-admin/plugins.php y updates.
	 */
	public function check_wp_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote_ver = $this->get_remote_version();
		if ( ! $remote_ver ) {
			return $transient;
		}

		if ( version_compare( COCO_COD_VERSION, $remote_ver, '<' ) ) {
			$plugin_slug = 'coco-cod/coco-cod.php';
			$transient->response[ $plugin_slug ] = (object) array(
				'slug'        => 'coco-cod',
				'plugin'      => $plugin_slug,
				'new_version' => $remote_ver,
				'url'         => 'https://github.com/' . self::GITHUB_REPO,
				'package'     => self::ZIP_URL,
				'icons'       => array(),
				'banners'     => array(),
			);
		}

		return $transient;
	}

	/**
	 * Información detallada del plugin para el modal de actualización de WordPress.
	 */
	public function plugins_api_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'coco-cod' !== $args->slug ) {
			return $result;
		}

		$remote_ver = $this->get_remote_version();

		return (object) array(
			'name'          => 'Coco COD — Pedido Contra Entrega para WooCommerce',
			'slug'          => 'coco-cod',
			'version'       => $remote_ver ? $remote_ver : COCO_COD_VERSION,
			'author'        => '<a href="https://github.com/denisparaguayo">Denis Paraguayo</a>',
			'homepage'      => 'https://github.com/' . self::GITHUB_REPO,
			'download_link' => self::ZIP_URL,
			'sections'      => array(
				'description' => 'Pedido contra entrega en 3 pasos con ubicación primero, flete en vivo por ciudad, compatibilidad nativa con Dropi y modo exclusivo COD 1 a 1.',
				'changelog'   => 'Ver repositorio oficial en GitHub: https://github.com/' . self::GITHUB_REPO,
			),
		);
	}

	/**
	 * Corrige la ruta de extracción si la actualización se ejecuta mediante el instalador nativo de WordPress.
	 */
	public function fix_update_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( isset( $hook_extra['plugin'] ) && 'coco-cod/coco-cod.php' === $hook_extra['plugin'] ) {
			if ( is_dir( $source . '/coco-cod' ) ) {
				return trailingslashit( $source . '/coco-cod' );
			}
		}
		return $source;
	}

	/**
	 * Renderiza la pestaña de Actualizaciones en la pantalla de administración de Coco COD.
	 */
	public function render_updates_tab() {
		$remote_ver = $this->get_remote_version();
		$current_ver = COCO_COD_VERSION;
		$has_update  = $remote_ver && version_compare( $current_ver, $remote_ver, '<' );
		?>
		<div class="coco-updates-container" style="max-width: 800px; margin-top: 20px;">

			<?php if ( isset( $_GET['updated'] ) && ! empty( $_GET['new_ver'] ) ) : ?>
				<div class="notice notice-success is-dismissible" style="border-left-color: #16a34a; padding: 12px;">
					<p style="font-size: 15px; margin: 0;">
						🎉 <strong><?php echo esc_html( sprintf( __( '¡Coco COD se ha actualizado con éxito a la versión %s directamente desde GitHub!', 'coco-cod' ), sanitize_text_field( $_GET['new_ver'] ) ) ); ?></strong>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['checked'] ) ) : ?>
				<div class="notice notice-info is-dismissible">
					<p><?php esc_html_e( 'Comprobación con GitHub completada correctamente.', 'coco-cod' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['error'] ) ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e( 'Error durante la actualización:', 'coco-cod' ); ?></strong> <?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p>
				</div>
			<?php endif; ?>

			<div class="card" style="padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
				<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px;">
					<div>
						<h2 style="margin: 0 0 6px 0; font-size: 20px; color: #1e293b;">
							<?php esc_html_e( 'Actualizador Directo desde GitHub', 'coco-cod' ); ?>
						</h2>
						<p style="margin: 0; color: #64748b;">
							<?php esc_html_e( 'Actualiza tu plugin automáticamente con 1 solo clic cada vez que hagas cambios en tu repositorio sin necesidad de subir archivos ZIP.', 'coco-cod' ); ?>
						</p>
					</div>
					<div>
						<span style="display: inline-block; padding: 6px 12px; border-radius: 9999px; font-weight: 600; font-size: 13px; <?php echo $has_update ? 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;' : 'background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;'; ?>">
							<?php echo $has_update ? '🔴 ' . esc_html__( 'Actualización disponible', 'coco-cod' ) : '🟢 ' . esc_html__( 'Plugin al día', 'coco-cod' ); ?>
						</span>
					</div>
				</div>

				<table class="widefat striped" style="margin-bottom: 24px;">
					<tbody>
						<tr>
							<td style="width: 250px; font-weight: 600;"><?php esc_html_e( 'Repositorio GitHub Oficial:', 'coco-cod' ); ?></td>
							<td>
								<a href="https://github.com/<?php echo esc_attr( self::GITHUB_REPO ); ?>" target="_blank" rel="noopener" style="font-family: monospace; font-size: 14px;">
									https://github.com/<?php echo esc_html( self::GITHUB_REPO ); ?> ↗
								</a>
								<span style="color: #64748b; font-size: 12px; margin-left: 8px;">(Rama: <code><?php echo esc_html( self::GITHUB_BRANCH ); ?></code>)</span>
							</td>
						</tr>
						<tr>
							<td style="font-weight: 600;"><?php esc_html_e( 'Versión actual instalada:', 'coco-cod' ); ?></td>
							<td>
								<strong style="font-size: 15px; color: #0f172a;">v<?php echo esc_html( $current_ver ); ?></strong>
							</td>
						</tr>
						<tr>
							<td style="font-weight: 600;"><?php esc_html_e( 'Última versión en GitHub:', 'coco-cod' ); ?></td>
							<td>
								<?php if ( $remote_ver ) : ?>
									<strong style="font-size: 15px; color: <?php echo $has_update ? '#b91c1c' : '#15803d'; ?>;">
										v<?php echo esc_html( $remote_ver ); ?>
									</strong>
									<?php if ( $has_update ) : ?>
										<span style="margin-left: 10px; color: #b91c1c; font-weight: 600;">(¡Nueva versión disponible!)</span>
									<?php else : ?>
										<span style="margin-left: 10px; color: #15803d; font-weight: 600;">(Coincide con tu versión actual)</span>
									<?php endif; ?>
								<?php else : ?>
									<span style="color: #64748b; font-style: italic;"><?php esc_html_e( 'No se pudo consultar GitHub en este momento.', 'coco-cod' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- Acciones -->
				<div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
					<?php if ( $has_update ) : ?>
						<form method="post" action="" style="margin: 0;">
							<?php wp_nonce_field( 'coco_cod_github_update_action', 'coco_cod_github_nonce' ); ?>
							<input type="hidden" name="coco_cod_github_action" value="update">
							<button type="submit" class="button button-primary button-hero" style="background: #16a34a; border-color: #15803d; box-shadow: none; display: inline-flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
								<?php echo esc_html( sprintf( __( 'Actualizar a v%s ahora', 'coco-cod' ), $remote_ver ) ); ?>
							</button>
						</form>
					<?php endif; ?>

					<form method="post" action="" style="margin: 0;">
						<?php wp_nonce_field( 'coco_cod_github_update_action', 'coco_cod_github_nonce' ); ?>
						<input type="hidden" name="coco_cod_github_action" value="check">
						<button type="submit" class="button <?php echo $has_update ? 'button-secondary' : 'button-primary'; ?>" style="height: 38px;">
							<span class="dashicons dashicons-search" style="margin-top: 4px;"></span>
							<?php esc_html_e( 'Buscar actualizaciones ahora', 'coco-cod' ); ?>
						</button>
					</form>

					<form method="post" action="" style="margin: 0;" onsubmit="return confirm('¿Seguro que deseas forzar la sincronización y sobreescribir los archivos actuales con los de tu rama main de GitHub?');">
						<?php wp_nonce_field( 'coco_cod_github_update_action', 'coco_cod_github_nonce' ); ?>
						<input type="hidden" name="coco_cod_github_action" value="force">
						<button type="submit" class="button button-secondary" style="height: 38px; color: #475569;" title="Útil si hiciste cambios en main sin cambiar el número de versión">
							<span class="dashicons dashicons-image-rotate" style="margin-top: 4px;"></span>
							<?php esc_html_e( 'Forzar sincronización / Reinstalar desde GitHub', 'coco-cod' ); ?>
						</button>
					</form>
				</div>

				<div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b;">
					<p style="margin: 0 0 4px 0;">
						💡 <strong><?php esc_html_e( '¿Cómo usarlo en tu flujo de trabajo?', 'coco-cod' ); ?></strong>
					</p>
					<ol style="margin: 4px 0 0 18px; padding: 0;">
						<li><?php esc_html_e( 'Haces tus cambios de código localmente y los subes a GitHub con git push.', 'coco-cod' ); ?></li>
						<li><?php esc_html_e( 'Vienes a esta pestaña y tocas "Buscar actualizaciones ahora" o "Forzar sincronización".', 'coco-cod' ); ?></li>
						<li><?php esc_html_e( '¡Listo! Tu WordPress descarga automáticamente la última versión desde GitHub sin necesidad de descargar o subir ningún archivo ZIP.', 'coco-cod' ); ?></li>
					</ol>
				</div>
			</div>
		</div>
		<?php
	}
}
