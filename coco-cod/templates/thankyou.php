<?php
/**
 * Plantilla moderna de confirmación / agradecimiento para pedidos Contra Entrega y Transferencia.
 *
 * @package Coco_COD
 * @var \WC_Order|false $order
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order instanceof \WC_Order ) {
	global $wp;
	$order_id_fallback  = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : ( isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0 );
	$order_key_fallback = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
	if ( $order_id_fallback > 0 ) {
		$candidate = wc_get_order( $order_id_fallback );
		if ( $candidate && ( empty( $order_key_fallback ) || hash_equals( $candidate->get_order_key(), $order_key_fallback ) ) ) {
			$order = $candidate;
		}
	}
}

if ( ! $order instanceof \WC_Order ) {
	return;
}

$plugin = \Coco_COD\Plugin::instance();

// Datos del pedido
$order_id   = $order->get_id();
$first_name = $order->get_billing_first_name();
$last_name  = $order->get_billing_last_name();
$full_name  = trim( $first_name . ' ' . $last_name );
$phone      = $order->get_billing_phone();
$email      = $order->get_billing_email();

$address_1  = $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1();
$address_2  = $order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_billing_address_2();
$city       = $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city();
$state_code = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();

// Obtener nombre del departamento desde las ubicaciones locales
$locations  = $plugin->get_locations();
$state_name = isset( $locations[ $state_code ]['name'] ) ? $locations[ $state_code ]['name'] : $state_code;

// Total y moneda
$total_formatted    = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
$subtotal_formatted = wc_price( $order->get_subtotal(), array( 'currency' => $order->get_currency() ) );
$shipping_cost      = (float) $order->get_shipping_total();
$shipping_formatted = $shipping_cost > 0 
	? wc_price( $shipping_cost, array( 'currency' => $order->get_currency() ) ) 
	: esc_html__( 'Gratis', 'coco-cod' );

// Determinar si es transferencia bancaria o pago contra entrega
$payment_type = $order->get_meta( '_coco_cod_payment_type' );
if ( ! $payment_type ) {
	$payment_type = ( 'bacs' === $order->get_payment_method() ) ? 'transfer' : 'cod';
}
$is_transfer = 'transfer' === $payment_type;

// Datos bancarios configurados
$bank_name     = $plugin->get_setting( 'bank_name', 'Ueno' );
$bank_holder   = $plugin->get_setting( 'bank_holder', 'MARIA ANASTASIA CONCEPCION PRIETO DE CANTERO' );
$bank_id       = $plugin->get_setting( 'bank_id', '1064733' );
$bank_account  = $plugin->get_setting( 'bank_account', '61965042' );
$bank_currency = $plugin->get_setting( 'bank_currency', 'Gs' );

// Enlace de WhatsApp dinámico según tipo de pago
$whatsapp_num   = $plugin->get_setting( 'whatsapp_number', '' );
$whatsapp_clean = preg_replace( '/\D/', '', $whatsapp_num );
$whatsapp_url   = '';
if ( ! empty( $whatsapp_clean ) ) {
	if ( $is_transfer ) {
		$wa_msg = sprintf(
			__( '¡Hola! Acabo de registrar el pedido #%1$s en su tienda por un total de %2$s vía Transferencia (%3$s). Mi nombre es %4$s. Adjunto mi comprobante para despachar mi paquete.', 'coco-cod' ),
			$order_id,
			wp_strip_all_tags( $total_formatted ),
			$bank_name,
			$full_name
		);
	} else {
		$wa_msg = sprintf(
			__( '¡Hola! Acabo de registrar el pedido #%1$s por un total de %2$s con Pago Contra Entrega. Mi nombre es %3$s.', 'coco-cod' ),
			$order_id,
			wp_strip_all_tags( $total_formatted ),
			$full_name
		);
	}
	$whatsapp_url = 'https://wa.me/' . $whatsapp_clean . '?text=' . rawurlencode( $wa_msg );
}

$shop_url = wc_get_page_permalink( 'shop' );
if ( ! $shop_url ) {
	$shop_url = home_url( '/' );
}

// Asegurar que los estilos estén encolados
if ( ! wp_style_is( 'coco-cod-css', 'enqueued' ) ) {
	wp_enqueue_style( 'coco-cod-css', COCO_COD_URL . 'assets/css/coco-cod.css', array(), COCO_COD_VERSION );
}
?>

<div class="coco-thankyou-wrapper" id="coco-receipt-area">
	<!-- TARJETA PRINCIPAL -->
	<div class="coco-thankyou-card">
		
		<!-- ENCABEZADO DE ÉXITO -->
		<div class="coco-thankyou-hero">
			<div class="coco-success-circle">
				<svg class="coco-checkmark-icon" viewBox="0 0 52 52" aria-hidden="true">
					<circle class="coco-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
					<path class="coco-checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
				</svg>
			</div>

			<div class="coco-order-badge-pill">
				<span class="coco-pulsing-dot" aria-hidden="true"></span>
				<strong><?php echo esc_html( sprintf( __( 'Pedido #%s Registrado con Éxito', 'coco-cod' ), $order_id ) ); ?></strong>
			</div>

			<h1 class="coco-thankyou-title">
				<?php 
				if ( ! empty( $first_name ) ) {
					echo esc_html( sprintf( __( '¡Muchas gracias por tu compra, %s!', 'coco-cod' ), $first_name ) );
				} else {
					esc_html_e( '¡Muchas gracias por tu compra!', 'coco-cod' );
				}
				?>
			</h1>

			<p class="coco-thankyou-lead">
				<?php if ( $is_transfer ) : ?>
					<?php esc_html_e( 'Tu pedido ha sido registrado con éxito. Por favor realizá tu transferencia con los datos de abajo y envianos el comprobante por WhatsApp para despachar de inmediato.', 'coco-cod' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Tu pedido está listo para ser coordinado. En breve nuestro equipo se pondrá en contacto contigo por WhatsApp o llamada para confirmar el día de entrega en tu puerta.', 'coco-cod' ); ?>
				<?php endif; ?>
			</p>
		</div>

		<!-- CONDICIÓN: TRANSFERENCIA vs PAGO EN EFECTIVO -->
		<?php if ( $is_transfer ) : ?>
			<!-- AVISO DESTACADO DE TRANSFERENCIA BANCARIA -->
			<div class="coco-bank-transfer-box">
				<div class="coco-bank-header">
					<div class="coco-bank-icon-svg" aria-hidden="true">
						<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M3 21h18M3 10h18M5 10v11M9 10v11M15 10v11M19 10v11M12 2L2 7h20L12 2z"/>
						</svg>
					</div>
					<div>
						<span class="coco-bank-badge"><?php esc_html_e( 'TRANSFERENCIA BANCARIA / PAGO QR', 'coco-cod' ); ?></span>
						<h3 class="coco-bank-title"><?php esc_html_e( 'Datos para realizar la transferencia:', 'coco-cod' ); ?></h3>
					</div>
				</div>

				<div class="coco-bank-table-card">
					<div class="coco-bt-row">
						<span class="coco-bt-label"><?php esc_html_e( 'Entidad / Banco:', 'coco-cod' ); ?></span>
						<strong class="coco-bt-value highlight"><?php echo esc_html( $bank_name ); ?></strong>
					</div>
					<div class="coco-bt-row">
						<span class="coco-bt-label"><?php esc_html_e( 'Titular de la Cuenta:', 'coco-cod' ); ?></span>
						<strong class="coco-bt-value"><?php echo esc_html( $bank_holder ); ?></strong>
					</div>
					<div class="coco-bt-row">
						<span class="coco-bt-label"><?php esc_html_e( 'Cédula de Identidad (C.I.):', 'coco-cod' ); ?></span>
						<strong class="coco-bt-value"><?php echo esc_html( $bank_id ); ?></strong>
					</div>
					<div class="coco-bt-row coco-acc-row">
						<span class="coco-bt-label"><?php esc_html_e( 'Nro. de Cuenta:', 'coco-cod' ); ?></span>
						<div class="coco-acc-copy-box">
							<strong class="coco-bt-value" id="coco-bank-acc"><?php echo esc_html( $bank_account ); ?></strong>
							<button type="button" class="coco-copy-btn" id="coco-btn-copy-acc" onclick="navigator.clipboard.writeText('<?php echo esc_js( $bank_account ); ?>'); this.classList.add('copied'); this.innerHTML='<svg width=14 height=14 viewBox=\'0 0 24 24\' fill=none stroke=currentColor stroke-width=3><path d=\'M20 6L9 17l-5-5\'/></svg> ¡Copiado!'; setTimeout(()=>{ this.classList.remove('copied'); this.innerHTML='<svg width=14 height=14 viewBox=\'0 0 24 24\' fill=none stroke=currentColor stroke-width=2><rect x=9 y=9 width=13 height=13 rx=2 ry=2/><path d=\'M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1\'/></svg> Copiar'; }, 2200);">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
									<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
									<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
								</svg>
								<?php esc_html_e( 'Copiar', 'coco-cod' ); ?>
							</button>
						</div>
					</div>
					<div class="coco-bt-row coco-total-row">
						<span class="coco-bt-label"><?php esc_html_e( 'Monto Exacto a Transferir:', 'coco-cod' ); ?></span>
						<strong class="coco-bt-value coco-transfer-amount"><?php echo wp_kses_post( $total_formatted ); ?></strong>
					</div>
				</div>

				<div class="coco-bank-wa-alert">
					<p>
						<span class="coco-alert-bullet" aria-hidden="true">💡</span>
						<strong><?php esc_html_e( 'Paso siguiente:', 'coco-cod' ); ?></strong>
						<?php esc_html_e( 'Una vez hecha la transferencia, por favor enviá tu comprobante por WhatsApp indicando tu número de pedido para despachar tu paquete inmediatamente.', 'coco-cod' ); ?>
					</p>
				</div>
			</div>
		<?php else : ?>
			<!-- AVISO DESTACADO DE PAGO EN EFECTIVO -->
			<div class="coco-payment-notice-box">
				<div class="coco-notice-icon-svg" aria-hidden="true">
					<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<rect x="2" y="6" width="20" height="12" rx="2"></rect>
						<circle cx="12" cy="12" r="3"></circle>
						<path d="M6 12h.01M18 12h.01"></path>
					</svg>
				</div>
				<div class="coco-notice-content">
					<span class="coco-notice-tag"><?php esc_html_e( 'MODALIDAD: PAGO CONTRA ENTREGA (EN EFECTIVO)', 'coco-cod' ); ?></span>
					<div class="coco-notice-total">
						<span><?php esc_html_e( 'Monto a pagar al recibir en tu puerta:', 'coco-cod' ); ?></span>
						<strong class="coco-total-highlight"><?php echo wp_kses_post( $total_formatted ); ?></strong>
					</div>
					<p class="coco-notice-tip">
						<?php esc_html_e( 'No pagás nada por adelantado. Por favor tené listo el importe en efectivo cuando el repartidor llegue a tu dirección.', 'coco-cod' ); ?>
					</p>
				</div>
			</div>
		<?php endif; ?>

		<!-- LÍNEA DE TIEMPO / ESTADOS -->
		<div class="coco-steps-tracker" aria-label="Progreso del pedido">
			<div class="coco-tracker-step completed">
				<div class="coco-tracker-dot" aria-hidden="true">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
				</div>
				<span class="coco-tracker-text"><?php esc_html_e( 'Pedido Recibido', 'coco-cod' ); ?></span>
			</div>
			<div class="coco-tracker-line active"></div>
			<div class="coco-tracker-step current">
				<div class="coco-tracker-dot" aria-hidden="true">
					<?php if ( $is_transfer ) : ?>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
					<?php else : ?>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
					<?php endif; ?>
				</div>
				<span class="coco-tracker-text"><?php echo $is_transfer ? esc_html__( 'Verificación Pago', 'coco-cod' ) : esc_html__( 'Coordinación', 'coco-cod' ); ?></span>
			</div>
			<div class="coco-tracker-line"></div>
			<div class="coco-tracker-step">
				<div class="coco-tracker-dot" aria-hidden="true">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
				</div>
				<span class="coco-tracker-text"><?php esc_html_e( 'En Camino', 'coco-cod' ); ?></span>
			</div>
			<div class="coco-tracker-line"></div>
			<div class="coco-tracker-step">
				<div class="coco-tracker-dot" aria-hidden="true">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
				</div>
				<span class="coco-tracker-text"><?php esc_html_e( 'Entregado', 'coco-cod' ); ?></span>
			</div>
		</div>

		<div class="coco-thankyou-grid">
			<!-- COLUMNA 1: DATOS DE ENTREGA -->
			<div class="coco-section-card">
				<h3 class="coco-card-heading">
					<span class="coco-heading-icon-svg" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
							<circle cx="12" cy="10" r="3"></circle>
						</svg>
					</span>
					<?php esc_html_e( 'Datos de Entrega', 'coco-cod' ); ?>
				</h3>
				
				<div class="coco-info-list">
					<div class="coco-info-item">
						<span class="coco-info-label"><?php esc_html_e( 'Destinatario:', 'coco-cod' ); ?></span>
						<strong class="coco-info-val"><?php echo esc_html( $full_name ); ?></strong>
					</div>

					<div class="coco-info-item">
						<span class="coco-info-label"><?php esc_html_e( 'Teléfono / WhatsApp:', 'coco-cod' ); ?></span>
						<strong class="coco-info-val"><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" class="coco-phone-link"><?php echo esc_html( $phone ); ?></a></strong>
					</div>

					<?php if ( $email ) : ?>
						<div class="coco-info-item">
							<span class="coco-info-label"><?php esc_html_e( 'Correo electrónico:', 'coco-cod' ); ?></span>
							<span class="coco-info-val"><?php echo esc_html( $email ); ?></span>
						</div>
					<?php endif; ?>

					<div class="coco-info-item">
						<span class="coco-info-label"><?php esc_html_e( 'Ciudad y Departamento:', 'coco-cod' ); ?></span>
						<span class="coco-info-val"><?php echo esc_html( $city . ', ' . $state_name ); ?></span>
					</div>

					<div class="coco-info-item">
						<span class="coco-info-label"><?php esc_html_e( 'Dirección exacta:', 'coco-cod' ); ?></span>
						<span class="coco-info-val"><?php echo esc_html( $address_1 ); ?></span>
					</div>

					<?php if ( ! empty( $address_2 ) ) : ?>
						<div class="coco-info-item coco-ref-item">
							<span class="coco-info-label"><?php esc_html_e( 'Punto de Referencia:', 'coco-cod' ); ?></span>
							<span class="coco-info-val"><em><?php echo esc_html( $address_2 ); ?></em></span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- COLUMNA 2: RESUMEN DE PRODUCTOS -->
			<div class="coco-section-card">
				<h3 class="coco-card-heading">
					<span class="coco-heading-icon-svg" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
							<line x1="3" y1="6" x2="21" y2="6"></line>
							<path d="M16 10a4 4 0 0 1-8 0"></path>
						</svg>
					</span>
					<?php esc_html_e( 'Resumen de Compra', 'coco-cod' ); ?>
				</h3>

				<div class="coco-items-list">
					<?php foreach ( $order->get_items() as $item_id => $item ) : ?>
						<?php 
							$product = $item->get_product(); 
							$image   = $product ? $product->get_image( array( 60, 60 ) ) : '';
						?>
						<div class="coco-thankyou-item">
							<?php if ( $image ) : ?>
								<div class="coco-item-thumb">
									<?php echo wp_kses_post( $image ); ?>
									<span class="coco-item-qty-pill"><?php echo esc_html( $item->get_quantity() ); ?></span>
								</div>
							<?php endif; ?>
							<div class="coco-item-details">
								<h4 class="coco-item-title"><?php echo esc_html( $item->get_name() ); ?></h4>
								<span class="coco-item-meta">
									<?php echo esc_html( sprintf( __( 'Cantidad: %s', 'coco-cod' ), $item->get_quantity() ) ); ?>
								</span>
							</div>
							<div class="coco-item-price">
								<?php echo wp_kses_post( wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="coco-totals-breakdown">
					<div class="coco-breakdown-row">
						<span><?php esc_html_e( 'Subtotal productos:', 'coco-cod' ); ?></span>
						<span><?php echo wp_kses_post( $subtotal_formatted ); ?></span>
					</div>
					<div class="coco-breakdown-row">
						<span><?php esc_html_e( 'Costo de envío:', 'coco-cod' ); ?></span>
						<span class="<?php echo ( 0 === (int) $shipping_cost ) ? 'coco-shipping-free-tag' : ''; ?>"><?php echo wp_kses_post( $shipping_formatted ); ?></span>
					</div>
					<div class="coco-breakdown-row coco-grand-total">
						<span><?php esc_html_e( 'Total a pagar:', 'coco-cod' ); ?></span>
						<strong class="coco-total-price"><?php echo wp_kses_post( $total_formatted ); ?></strong>
					</div>
				</div>
			</div>
		</div>

		<!-- BOTONES DE ACCIÓN POST-COMPRA -->
		<div class="coco-thankyou-actions">
			<?php if ( ! empty( $whatsapp_url ) ) : ?>
				<a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener" class="coco-btn-whatsapp">
					<svg class="coco-btn-wa-svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
						<path d="M17.5 12c0-.3-.1-.5-.3-.6l-2-1c-.2-.1-.5-.1-.7.1l-.8 1c-.1.1-.3.2-.5.1-1.1-.5-2-1.4-2.5-2.5-.1-.2 0-.4.1-.5l1-.8c.2-.2.2-.5.1-.7l-1-2c-.1-.2-.4-.3-.7-.3h-.5c-.6 0-1.1.5-1.1 1.1 0 1.9 1 3.8 2.6 5.4 1.6 1.6 3.5 2.6 5.4 2.6.6 0 1.1-.5 1.1-1.1v-.5c0-.1 0-.2-.1-.3-.1-.1-.2-.1-.3-.1zm-5.5-9.8C6.6 2.2 2.2 6.6 2.2 12c0 1.9.5 3.6 1.5 5.1L2 22l5-1.6c1.4.9 3.1 1.4 5 1.4 5.4 0 9.8-4.4 9.8-9.8S17.4 2.2 12 2.2zm0 17.6c-1.6 0-3.1-.5-4.4-1.3l-.3-.2-3.3 1.1 1.1-3.2-.2-.3c-.9-1.3-1.4-2.9-1.4-4.5 0-4.6 3.7-8.3 8.3-8.3 4.6 0 8.3 3.7 8.3 8.3s-3.7 8.4-8.1 8.4z"/>
					</svg>
					<?php echo $is_transfer ? esc_html__( 'Enviar comprobante por WhatsApp', 'coco-cod' ) : esc_html__( 'Confirmar pedido por WhatsApp', 'coco-cod' ); ?>
				</a>
			<?php endif; ?>

			<div class="coco-secondary-actions">
				<button type="button" class="coco-btn-print" onclick="window.print();">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<polyline points="6 9 6 2 18 2 18 9"></polyline>
						<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
						<rect x="6" y="14" width="12" height="8"></rect>
					</svg>
					<?php esc_html_e( 'Guardar o imprimir comprobante', 'coco-cod' ); ?>
				</button>

				<a href="<?php echo esc_url( $shop_url ); ?>" class="coco-btn-return">
					&larr; <?php esc_html_e( 'Volver a la tienda', 'coco-cod' ); ?>
				</a>
			</div>
		</div>

		<!-- BANNER DE GARANTÍA Y CONFIANZA -->
		<div class="coco-guarantee-footer">
			<div class="coco-guarantee-icon" aria-hidden="true">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
					<path d="M9 12l2 2 4-4"></path>
				</svg>
			</div>
			<div class="coco-guarantee-text">
				<strong><?php esc_html_e( 'Garantía Coco Cherry Store', 'coco-cod' ); ?></strong>
				<span><?php esc_html_e( 'Tu compra es 100% segura. Ante cualquier duda o cambio de horario de entrega, escribinos a nuestro WhatsApp de atención al cliente.', 'coco-cod' ); ?></span>
			</div>
		</div>

	</div>
</div>

<?php
/**
 * IMPORTANTE PARA MARKETING Y ANALÍTICA:
 * Mantenemos la ejecución del hook estándar de WooCommerce para que Facebook Pixel / Google Ads / TikTok
 * disparen su evento Purchase con el ID del pedido y valor exacto.
 * Removemos las tablas visuales redundantes de WooCommerce para mostrar exclusivamente nuestra tarjeta limpia.
 */
remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
do_action( 'woocommerce_thankyou', $order->get_id() );
?>
