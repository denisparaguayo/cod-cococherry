<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plantilla del Modal de 3 Pasos de Coco COD.
 */
?>
<div id="coco-cod-modal" class="coco-cod-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="coco-modal-title">
	<div class="coco-cod-backdrop" data-coco-close></div>

	<div class="coco-cod-dialog">
		<!-- Botón Cerrar -->
		<button type="button" class="coco-cod-close" data-coco-close aria-label="<?php esc_attr_e( 'Cerrar ventana', 'coco-cod' ); ?>">
			&times;
		</button>

		<!-- Cabecera de Producto -->
		<div class="coco-cod-product-bar">
			<div class="coco-cod-product-thumb" id="coco-thumb-container">
				<?php echo $product->get_image( 'thumbnail' ); ?>
			</div>
			<div class="coco-cod-product-info">
				<h3 class="coco-cod-product-title"><?php echo esc_html( $product->get_name() ); ?></h3>
				<div class="coco-cod-product-meta">
					<span id="coco-selected-variation" style="display:none;"></span>
					<span class="coco-product-unit-price"><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></span>
					<span class="coco-product-qty-badge">&times; <span id="coco-summary-qty">1</span></span>
				</div>
			</div>
		</div>

		<!-- Barra de Progreso de 3 Pasos -->
		<div class="coco-cod-steps-bar">
			<div class="coco-step-item active" data-step="1">
				<span class="coco-step-num">1</span>
				<span class="coco-step-label"><?php esc_html_e( 'Entrega', 'coco-cod' ); ?></span>
			</div>
			<div class="coco-step-line"></div>
			<div class="coco-step-item" data-step="2">
				<span class="coco-step-num">2</span>
				<span class="coco-step-label"><?php esc_html_e( 'Datos', 'coco-cod' ); ?></span>
			</div>
			<div class="coco-step-line"></div>
			<div class="coco-step-item" data-step="3">
				<span class="coco-step-num">3</span>
				<span class="coco-step-label"><?php esc_html_e( 'Confirmar', 'coco-cod' ); ?></span>
			</div>
		</div>

		<!-- Contenedor de Errores Inline -->
		<div id="coco-modal-errors" class="coco-modal-alert error" style="display:none;" aria-live="assertive"></div>

		<!-- Formulario Principal -->
		<form id="coco-cod-order-form" autocomplete="on">
			<!-- Campos ocultos de seguridad y control -->
			<input type="text" name="coco_hp_code" value="" style="display:none !important;" tabindex="-1" autocomplete="off">
			<input type="hidden" name="form_rendered_at" id="coco-rendered-at" value="">
			<input type="hidden" name="idempotency_key" id="coco-idempotency-key" value="">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">
			<input type="hidden" name="variation_id" id="coco-form-variation-id" value="0">
			<input type="hidden" name="quantity" id="coco-form-qty" value="1">

			<!-- PASO 1: ENTREGA -->
			<div class="coco-step-pane active" id="coco-step-1">
				<h4 class="coco-step-title"><?php esc_html_e( '¿Dónde quieres recibir tu pedido?', 'coco-cod' ); ?></h4>

				<div class="coco-field-group">
					<label for="coco_state"><?php esc_html_e( 'Departamento *', 'coco-cod' ); ?></label>
					<select id="coco_state" name="state" class="coco-input" required>
						<option value=""><?php esc_html_e( '-- Selecciona tu Departamento --', 'coco-cod' ); ?></option>
						<?php foreach ( $locations as $code => $data ) : ?>
							<?php
							// Si es Dropi, ocultar Alto Paraguay (16)
							if ( $is_dropi && '16' === (string) $code ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $data['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="coco-field-group">
					<label for="coco_city"><?php esc_html_e( 'Ciudad / Distrito *', 'coco-cod' ); ?></label>
					<select id="coco_city" name="city" class="coco-input" required disabled>
						<option value=""><?php esc_html_e( '-- Selecciona el departamento primero --', 'coco-cod' ); ?></option>
					</select>
				</div>

				<div class="coco-field-group">
					<label for="coco_address_1"><?php esc_html_e( 'Dirección exacta (Calle y N° de casa) *', 'coco-cod' ); ?></label>
					<input type="text" id="coco_address_1" name="address_1" class="coco-input" placeholder="<?php esc_attr_e( 'Ej: Avda. Mariscal López 1234 c/ San Martín', 'coco-cod' ); ?>" required>
				</div>

				<?php if ( $allow_reference ) : ?>
					<div class="coco-field-group">
						<label for="coco_address_2"><?php esc_html_e( 'Punto de referencia (Opcional)', 'coco-cod' ); ?></label>
						<input type="text" id="coco_address_2" name="address_2" class="coco-input" placeholder="<?php esc_attr_e( 'Ej: Portón verde, frente a la farmacia', 'coco-cod' ); ?>">
					</div>
				<?php endif; ?>

				<!-- Resumen de flete en tiempo real -->
				<div class="coco-shipping-preview-box">
					<div class="coco-preview-line">
						<span><?php esc_html_e( 'Costo de envío estimado:', 'coco-cod' ); ?></span>
						<strong id="coco-live-shipping"><?php esc_html_e( 'Selecciona tu ciudad', 'coco-cod' ); ?></strong>
					</div>
				</div>

				<div class="coco-step-actions">
					<button type="button" class="coco-btn-next coco-primary-btn" id="coco-go-step-2">
						<?php esc_html_e( 'Continuar a Mis Datos', 'coco-cod' ); ?> &rarr;
					</button>
				</div>
			</div>

			<!-- PASO 2: DATOS -->
			<div class="coco-step-pane" id="coco-step-2">
				<h4 class="coco-step-title"><?php esc_html_e( '¿A nombre de quién entregamos?', 'coco-cod' ); ?></h4>

				<div class="coco-row">
					<div class="coco-col">
						<div class="coco-field-group">
							<label for="coco_first_name"><?php esc_html_e( 'Nombre *', 'coco-cod' ); ?></label>
							<input type="text" id="coco_first_name" name="first_name" class="coco-input" autocomplete="given-name" placeholder="<?php esc_attr_e( 'Tu nombre', 'coco-cod' ); ?>" required>
						</div>
					</div>
					<div class="coco-col">
						<div class="coco-field-group">
							<label for="coco_last_name"><?php esc_html_e( 'Apellido *', 'coco-cod' ); ?></label>
							<input type="text" id="coco_last_name" name="last_name" class="coco-input" autocomplete="family-name" placeholder="<?php esc_attr_e( 'Tu apellido', 'coco-cod' ); ?>" required>
						</div>
					</div>
				</div>

				<div class="coco-field-group">
					<label for="coco_phone"><?php esc_html_e( 'Número de Celular *', 'coco-cod' ); ?></label>
					<input type="tel" id="coco_phone" name="phone" class="coco-input" autocomplete="tel" placeholder="<?php esc_attr_e( 'Ej: 0981 123 456', 'coco-cod' ); ?>" required>
					<small class="coco-field-hint"><?php esc_html_e( 'Te llamaremos o escribiremos para coordinar la entrega.', 'coco-cod' ); ?></small>
				</div>

				<div class="coco-field-group">
					<label for="coco_email"><?php esc_html_e( 'Correo Electrónico *', 'coco-cod' ); ?></label>
					<input type="email" id="coco_email" name="email" class="coco-input" autocomplete="email" placeholder="<?php esc_attr_e( 'ejemplo@gmail.com', 'coco-cod' ); ?>" required>
					<small class="coco-field-hint"><?php esc_html_e( 'Te enviaremos el comprobante y seguimiento de tu orden.', 'coco-cod' ); ?></small>
				</div>

				<div class="coco-step-actions coco-actions-split">
					<button type="button" class="coco-btn-back" data-goto="1">&larr; <?php esc_html_e( 'Volver', 'coco-cod' ); ?></button>
					<button type="button" class="coco-btn-next coco-primary-btn" id="coco-go-step-3">
						<?php esc_html_e( 'Revisar Pedido', 'coco-cod' ); ?> &rarr;
					</button>
				</div>
			</div>

			<!-- PASO 3: CONFIRMAR -->
			<div class="coco-step-pane" id="coco-step-3">
				<h4 class="coco-step-title"><?php esc_html_e( 'Resumen y Confirmación', 'coco-cod' ); ?></h4>

				<div class="coco-summary-card">
					<div class="coco-summary-section">
						<strong><?php esc_html_e( 'Lugar de entrega:', 'coco-cod' ); ?></strong>
						<div id="coco-summary-dest" class="coco-summary-text">-</div>
						<div id="coco-summary-addr" class="coco-summary-subtext">-</div>
					</div>

					<div class="coco-summary-section">
						<strong><?php esc_html_e( 'Destinatario:', 'coco-cod' ); ?></strong>
						<div id="coco-summary-client" class="coco-summary-text">-</div>
						<div id="coco-summary-contact" class="coco-summary-subtext">-</div>
					</div>

					<div class="coco-pricing-table">
						<div class="coco-price-row">
							<span><?php esc_html_e( 'Subtotal productos:', 'coco-cod' ); ?></span>
							<span id="coco-final-subtotal">Gs. 0</span>
						</div>
						<div class="coco-price-row">
							<span><?php esc_html_e( 'Costo de envío:', 'coco-cod' ); ?></span>
							<span id="coco-final-shipping">Gs. 0</span>
						</div>
						<div class="coco-price-row coco-price-total">
							<span><?php esc_html_e( 'Total a pagar:', 'coco-cod' ); ?></span>
							<span id="coco-final-total">Gs. 0</span>
						</div>
					</div>
				</div>

				<div class="coco-cod-badge-box">
					<span class="coco-badge-icon">💵</span>
					<span><?php esc_html_e( 'Pagas en efectivo al recibir el paquete en tus manos.', 'coco-cod' ); ?></span>
				</div>

				<div class="coco-step-actions coco-actions-split">
					<button type="button" class="coco-btn-back" data-goto="2">&larr; <?php esc_html_e( 'Modificar Datos', 'coco-cod' ); ?></button>
					<button type="submit" class="coco-primary-btn coco-btn-submit" id="coco-btn-submit-order">
						<span class="coco-submit-label"><?php esc_html_e( 'Confirmar pedido', 'coco-cod' ); ?></span>
						<span class="coco-submit-spinner" style="display:none;">⏳ <?php esc_html_e( 'Procesando...', 'coco-cod' ); ?></span>
					</button>
				</div>
			</div>
		</form>
	</div>
</div>
