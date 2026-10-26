<?php
namespace Coco_COD;

defined( 'ABSPATH' ) || exit;

/**
 * Gestión del panel de administración y ajustes de Coco COD.
 */
class Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Coco COD — Pedido Contra Entrega', 'coco-cod' ),
			__( 'Coco COD', 'coco-cod' ),
			'manage_woocommerce',
			'coco-cod',
			array( $this, 'render_admin_page' )
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'woocommerce_page_coco-cod' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Script inline para color-picker y selector dependiente de ciudades en admin
		wp_add_inline_script( 'wp-color-picker', $this->get_admin_inline_js() );
	}

	private function get_admin_inline_js() {
		$locations = Plugin::instance()->get_locations();
		$json_locations = wp_json_encode( $locations );

		return "
		jQuery(document).ready(function($) {
			// Color picker
			$('.coco-color-picker').wpColorPicker({
				change: function(event, ui) {
					updateButtonPreview();
				}
			});

			// Vista previa de botón
			function updateButtonPreview() {
				var bg = $('#btn_bg_color').val();
				var color = $('#btn_text_color').val();
				var radius = $('#btn_radius').val();
				var text = $('#btn_text').val();
				var sub = $('#btn_subtitle').val();

				var preview = $('#coco-btn-preview');
				if (preview.length) {
					preview.css({
						'background-color': bg,
						'color': color,
						'border-radius': radius
					});
					preview.find('.coco-preview-text').text(text);
					preview.find('.coco-preview-sub').text(sub);
				}
			}

			$('#btn_radius, #btn_text, #btn_subtitle').on('input change', updateButtonPreview);
			updateButtonPreview();

			// Ciudades dependientes para tarifas de envío
			var pyLocations = {$json_locations};

			$('#new_rate_state').on('change', function() {
				var stateCode = $(this).val();
				var citySelect = $('#new_rate_city');
				citySelect.empty();

				if (pyLocations[stateCode] && pyLocations[stateCode].cities) {
					citySelect.append('<option value=\"\">-- Seleccionar Ciudad --</option>');
					$.each(pyLocations[stateCode].cities, function(i, city) {
						citySelect.append('<option value=\"' + city + '\">' + city + '</option>');
					});
					citySelect.prop('disabled', false);
				} else {
					citySelect.append('<option value=\"\">-- Seleccione un departamento primero --</option>');
					citySelect.prop('disabled', true);
				}
			});

			// Agregar tarifa por ciudad
			$('#add_city_rate_btn').on('click', function(e) {
				e.preventDefault();
				var state = $('#new_rate_state').val();
				var stateName = $('#new_rate_state option:selected').text();
				var city = $('#new_rate_city').val();
				var cost = $('#new_rate_cost').val();

				if (!state || !city || !cost) {
					alert('Por favor complete el departamento, la ciudad y el precio en Gs.');
					return;
				}

				var index = $('#coco-city-rates-table tbody tr').length;
				var rowHtml = '<tr>' +
					'<td>' + stateName + '<input type=\"hidden\" name=\"city_rates[' + index + '][state]\" value=\"' + state + '\"></td>' +
					'<td><strong>' + city + '</strong><input type=\"hidden\" name=\"city_rates[' + index + '][city]\" value=\"' + city + '\"></td>' +
					'<td>Gs. <input type=\"number\" name=\"city_rates[' + index + '][cost]\" value=\"' + cost + '\" style=\"width:130px;\"></td>' +
					'<td><button type=\"button\" class=\"button coco-remove-rate-btn\" style=\"color:#b91c1c;\">Eliminar</button></td>' +
				'</tr>';

				$('#coco-city-rates-table tbody').append(rowHtml);
				$('#new_rate_city').val('');
				$('#new_rate_cost').val('');
			});

			// Eliminar fila
			$(document).on('click', '.coco-remove-rate-btn', function() {
				$(this).closest('tr').remove();
			});
		});
		";
	}

	public function save_settings() {
		if ( ! isset( $_POST['coco_cod_save_settings'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'coco_cod_settings_action', 'coco_cod_settings_nonce' ) ) {
			wp_die( 'Acceso inválido.' );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'No tienes permisos para modificar estos ajustes.' );
		}

		$current = get_option( 'coco_cod_settings', array() );

		// Procesar pestaña general y apariencia
		$current['enabled']           = isset( $_POST['enabled'] ) ? 'yes' : 'no';
		$current['test_mode']         = isset( $_POST['test_mode'] ) ? 'yes' : 'no';
		$current['hide_add_to_cart']  = isset( $_POST['hide_add_to_cart'] ) ? 'yes' : 'no';
		$current['allow_reference']   = isset( $_POST['allow_reference'] ) ? 'yes' : 'no';

		if ( isset( $_POST['btn_text'] ) ) {
			$current['btn_text'] = sanitize_text_field( wp_unslash( $_POST['btn_text'] ) );
		}
		if ( isset( $_POST['btn_subtitle'] ) ) {
			$current['btn_subtitle'] = sanitize_text_field( wp_unslash( $_POST['btn_subtitle'] ) );
		}
		if ( isset( $_POST['btn_bg_color'] ) ) {
			$current['btn_bg_color'] = sanitize_hex_color( wp_unslash( $_POST['btn_bg_color'] ) );
		}
		if ( isset( $_POST['btn_text_color'] ) ) {
			$current['btn_text_color'] = sanitize_hex_color( wp_unslash( $_POST['btn_text_color'] ) );
		}
		if ( isset( $_POST['btn_hover_bg'] ) ) {
			$current['btn_hover_bg'] = sanitize_hex_color( wp_unslash( $_POST['btn_hover_bg'] ) );
		}
		if ( isset( $_POST['btn_hover_text'] ) ) {
			$current['btn_hover_text'] = sanitize_hex_color( wp_unslash( $_POST['btn_hover_text'] ) );
		}
		if ( isset( $_POST['btn_radius'] ) ) {
			$current['btn_radius'] = sanitize_text_field( wp_unslash( $_POST['btn_radius'] ) );
		}

		// Procesar pestaña envíos
		if ( isset( $_POST['shipping_central'] ) ) {
			$current['shipping_central'] = absint( $_POST['shipping_central'] );
		}
		if ( isset( $_POST['shipping_interior'] ) ) {
			$current['shipping_interior'] = absint( $_POST['shipping_interior'] );
		}

		// Procesar tabla de tarifas por ciudad
		$sanitized_city_rates = array();
		if ( isset( $_POST['city_rates'] ) && is_array( $_POST['city_rates'] ) ) {
			foreach ( $_POST['city_rates'] as $rate ) {
				if ( ! empty( $rate['city'] ) && isset( $rate['cost'] ) ) {
					$sanitized_city_rates[] = array(
						'state' => sanitize_text_field( $rate['state'] ),
						'city'  => sanitize_text_field( $rate['city'] ),
						'cost'  => absint( $rate['cost'] ),
					);
				}
			}
		}
		$current['city_rates'] = $sanitized_city_rates;

		update_option( 'coco_cod_settings', $current );
		Plugin::instance()->reload_settings();

		wp_safe_redirect( add_query_arg( array( 'page' => 'coco-cod', 'tab' => isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		$settings = Plugin::instance();
		$locations = $settings->get_locations();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Coco COD — Pedido Contra Entrega', 'coco-cod' ); ?></h1>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Ajustes guardados correctamente.', 'coco-cod' ); ?></p>
				</div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=coco-cod&tab=general' ) ); ?>" class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'coco-cod' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=coco-cod&tab=appearance' ) ); ?>" class="nav-tab <?php echo 'appearance' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Apariencia y Botones', 'coco-cod' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=coco-cod&tab=shipping' ) ); ?>" class="nav-tab <?php echo 'shipping' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Tarifas de Envío por Ciudad', 'coco-cod' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=coco-cod&tab=health' ) ); ?>" class="nav-tab <?php echo 'health' === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Diagnóstico / Health Check', 'coco-cod' ); ?>
				</a>
			</nav>

			<?php if ( 'health' === $tab ) : ?>
				<?php Plugin::instance()->health_module->render_health_tab(); ?>
			<?php else : ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'coco_cod_settings_action', 'coco_cod_settings_nonce' ); ?>
					<input type="hidden" name="coco_cod_save_settings" value="1">

					<?php if ( 'general' === $tab ) : ?>
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Activar Plugin', 'coco-cod' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="enabled" value="yes" <?php checked( $settings->get_setting( 'enabled', 'yes' ), 'yes' ); ?>>
										<?php esc_html_e( 'Habilitar el botón y modal de compra rápida contra entrega en las fichas de producto.', 'coco-cod' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Modo de Pruebas Seguro', 'coco-cod' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="test_mode" value="yes" <?php checked( $settings->get_setting( 'test_mode', 'no' ), 'yes' ); ?>>
										<?php esc_html_e( 'Activar modo de prueba (solo los administradores o visitas con ?coco_test=1 verán el modal; los clientes verán la tienda normal).', 'coco-cod' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Ocultar botón "Añadir al carrito"', 'coco-cod' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="hide_add_to_cart" value="yes" <?php checked( $settings->get_setting( 'hide_add_to_cart', 'no' ), 'yes' ); ?>>
										<?php esc_html_e( 'Ocultar el botón nativo de añadir al carrito para dejar únicamente el botón de pago contra entrega en la ficha de producto.', 'coco-cod' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Campo de Referencia', 'coco-cod' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="allow_reference" value="yes" <?php checked( $settings->get_setting( 'allow_reference', 'yes' ), 'yes' ); ?>>
										<?php esc_html_e( 'Permitir al cliente agregar una referencia de su casa/ubicación (ej. frente a la plaza).', 'coco-cod' ); ?>
									</label>
								</td>
							</tr>
						</table>

					<?php elseif ( 'appearance' === $tab ) : ?>
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Texto Principal del Botón', 'coco-cod' ); ?></th>
								<td>
									<input type="text" id="btn_text" name="btn_text" value="<?php echo esc_attr( $settings->get_setting( 'btn_text', 'Pagar al recibir en casa' ) ); ?>" class="regular-text">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Subtítulo del Botón', 'coco-cod' ); ?></th>
								<td>
									<input type="text" id="btn_subtitle" name="btn_subtitle" value="<?php echo esc_attr( $settings->get_setting( 'btn_subtitle', 'Envío rápido · Pago en efectivo' ) ); ?>" class="regular-text">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Color de Fondo', 'coco-cod' ); ?></th>
								<td>
									<input type="text" id="btn_bg_color" name="btn_bg_color" value="<?php echo esc_attr( $settings->get_setting( 'btn_bg_color', '#16a34a' ) ); ?>" class="coco-color-picker">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Color del Texto', 'coco-cod' ); ?></th>
								<td>
									<input type="text" id="btn_text_color" name="btn_text_color" value="<?php echo esc_attr( $settings->get_setting( 'btn_text_color', '#ffffff' ) ); ?>" class="coco-color-picker">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Color de Fondo (Hover)', 'coco-cod' ); ?></th>
								<td>
									<input type="text" id="btn_hover_bg" name="btn_hover_bg" value="<?php echo esc_attr( $settings->get_setting( 'btn_hover_bg', '#15803d' ) ); ?>" class="coco-color-picker">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Color de Texto (Hover)', 'coco-cod' ); ?></th>
								<td>
									<input type="text" id="btn_hover_text" name="btn_hover_text" value="<?php echo esc_attr( $settings->get_setting( 'btn_hover_text', '#ffffff' ) ); ?>" class="coco-color-picker">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Redondeo de Bordes', 'coco-cod' ); ?></th>
								<td>
									<select id="btn_radius" name="btn_radius">
										<option value="0px" <?php selected( $settings->get_setting( 'btn_radius', '8px' ), '0px' ); ?>><?php esc_html_e( 'Recto (0px)', 'coco-cod' ); ?></option>
										<option value="4px" <?php selected( $settings->get_setting( 'btn_radius', '8px' ), '4px' ); ?>><?php esc_html_e( 'Leve (4px)', 'coco-cod' ); ?></option>
										<option value="8px" <?php selected( $settings->get_setting( 'btn_radius', '8px' ), '8px' ); ?>><?php esc_html_e( 'Estándar Suave (8px)', 'coco-cod' ); ?></option>
										<option value="14px" <?php selected( $settings->get_setting( 'btn_radius', '8px' ), '14px' ); ?>><?php esc_html_e( 'Redondeado (14px)', 'coco-cod' ); ?></option>
										<option value="9999px" <?php selected( $settings->get_setting( 'btn_radius', '8px' ), '9999px' ); ?>><?php esc_html_e( 'Estilo Píldora (Redondo)', 'coco-cod' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Vista Previa en Vivo', 'coco-cod' ); ?></th>
								<td>
									<div style="padding:20px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; display:inline-block; min-width:320px;">
										<div id="coco-btn-preview" style="padding:14px 20px; text-align:center; font-weight:bold; cursor:pointer; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
											<div class="coco-preview-text" style="font-size:16px;"></div>
											<div class="coco-preview-sub" style="font-size:12px; opacity:0.85; margin-top:2px;"></div>
										</div>
									</div>
								</td>
							</tr>
						</table>

					<?php elseif ( 'shipping' === $tab ) : ?>
						<h2><?php esc_html_e( 'Tarifas Generales de Envío (Stock Propio)', 'coco-cod' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Estas tarifas aplican para tus productos propios con clase "Stock Propio". Los productos de Dropi se mantienen con envío en Gs 0.', 'coco-cod' ); ?></p>

						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Tarifa Capital y Central', 'coco-cod' ); ?></th>
								<td>
									Gs. <input type="number" name="shipping_central" value="<?php echo esc_attr( $settings->get_setting( 'shipping_central', '35000' ) ); ?>" class="regular-text" style="width:140px;">
									<p class="description"><?php esc_html_e( 'Aplica para Asunción y el Departamento Central (salvo las ciudades que tengan una tarifa especial debajo).', 'coco-cod' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Tarifa Interior del País', 'coco-cod' ); ?></th>
								<td>
									Gs. <input type="number" name="shipping_interior" value="<?php echo esc_attr( $settings->get_setting( 'shipping_interior', '45000' ) ); ?>" class="regular-text" style="width:140px;">
									<p class="description"><?php esc_html_e( 'Aplica para el resto de los departamentos de Paraguay.', 'coco-cod' ); ?></p>
								</td>
							</tr>
						</table>

						<hr style="margin:25px 0;">

						<h2><?php esc_html_e( 'Tarifas de Envío por Ciudad Específica', 'coco-cod' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Aquí puedes cambiar el precio de envío de cualquier ciudad o agregar nuevas excepciones. Si una ciudad está en esta lista, se cobrará exactamente este costo.', 'coco-cod' ); ?></p>

						<div style="background:#fff; border:1px solid #ccd0d4; padding:15px; border-radius:6px; margin-bottom:20px; max-width:850px;">
							<h3 style="margin-top:0;"><?php esc_html_e( '+ Agregar o Modificar Tarifa para una Ciudad', 'coco-cod' ); ?></h3>
							<div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
								<select id="new_rate_state" style="min-width:180px;">
									<option value=""><?php esc_html_e( '-- Seleccionar Departamento --', 'coco-cod' ); ?></option>
									<?php foreach ( $locations as $code => $data ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $data['name'] ); ?></option>
									<?php endforeach; ?>
								</select>

								<select id="new_rate_city" style="min-width:180px;" disabled>
									<option value=""><?php esc_html_e( '-- Seleccione Departamento primero --', 'coco-cod' ); ?></option>
								</select>

								<span>Gs. <input type="number" id="new_rate_cost" placeholder="Ej: 45000" style="width:120px;"></span>

								<button type="button" id="add_city_rate_btn" class="button button-primary">
									<?php esc_html_e( 'Agregar Ciudad', 'coco-cod' ); ?>
								</button>
							</div>
						</div>

						<table class="widefat striped" id="coco-city-rates-table" style="max-width:850px;">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Departamento', 'coco-cod' ); ?></th>
									<th><?php esc_html_e( 'Ciudad / Distrito', 'coco-cod' ); ?></th>
									<th><?php esc_html_e( 'Costo de Envío (Gs)', 'coco-cod' ); ?></th>
									<th style="width:90px;"><?php esc_html_e( 'Acción', 'coco-cod' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$city_rates = $settings->get_setting( 'city_rates', array(
									array( 'state' => '11', 'city' => 'Villeta', 'cost' => '45000' ),
									array( 'state' => '11', 'city' => 'Nueva Italia', 'cost' => '45000' ),
									array( 'state' => '11', 'city' => 'Ypacaraí', 'cost' => '45000' ),
								) );

								if ( ! empty( $city_rates ) ) :
									foreach ( $city_rates as $idx => $item ) :
										$st_code = isset( $item['state'] ) ? $item['state'] : '';
										$st_name = isset( $locations[ $st_code ]['name'] ) ? $locations[ $st_code ]['name'] : $st_code;
										$city    = isset( $item['city'] ) ? $item['city'] : '';
										$cost    = isset( $item['cost'] ) ? $item['cost'] : 0;
										?>
										<tr>
											<td>
												<?php echo esc_html( $st_name ); ?>
												<input type="hidden" name="city_rates[<?php echo esc_attr( $idx ); ?>][state]" value="<?php echo esc_attr( $st_code ); ?>">
											</td>
											<td>
												<strong><?php echo esc_html( $city ); ?></strong>
												<input type="hidden" name="city_rates[<?php echo esc_attr( $idx ); ?>][city]" value="<?php echo esc_attr( $city ); ?>">
											</td>
											<td>
												Gs. <input type="number" name="city_rates[<?php echo esc_attr( $idx ); ?>][cost]" value="<?php echo esc_attr( $cost ); ?>" style="width:130px;">
											</td>
											<td>
												<button type="button" class="button coco-remove-rate-btn" style="color:#b91c1c;">
													<?php esc_html_e( 'Eliminar', 'coco-cod' ); ?>
												</button>
											</td>
										</tr>
										<?php
									endforeach;
								endif;
								?>
							</tbody>
						</table>
					<?php endif; ?>

					<p class="submit">
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Guardar Cambios', 'coco-cod' ); ?>
						</button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
