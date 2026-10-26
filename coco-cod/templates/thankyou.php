<?php
/**
 * Plantilla moderna de confirmación / agradecimiento para pedidos Contra Entrega y Transferencia.
 *
 * @var \WC_Order|false $order
 */
defined( 'ABSPATH' ) || exit;

if ( ! $order instanceof \WC_Order ) {
	return;
}

$plugin = \Coco_COD\Plugin::instance();

// Datos del pedido
$order_id       = $order->get_id();
$first_name     = $order->get_billing_first_name();
$last_name      = $order->get_billing_last_name();
$full_name      = trim( $first_name . ' ' . $last_name );
$phone          = $order->get_billing_phone();
$email          = $order->get_billing_email();

$address_1      = $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1();
$address_2      = $order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_billing_address_2();
$city           = $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city();
$state_code     = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();

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
			__( '¡Hola! Acabo de realizar el pedido #%1$s en su tienda por un total de %2$s mediante Transferencia Bancaria (%3$s). Mi nombre es %4$s. Aquí adjunto el comprobante.', 'coco-cod' ),
			$order_id,
			wp_strip_all_tags( $total_formatted ),
			$bank_name,
			$full_name
		);
	} else {
		$wa_msg = sprintf(
			__( '¡Hola! Acabo de realizar el pedido #%1$s en su tienda por un total de %2$s. Mi nombre es %3$s.', 'coco-cod' ),
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
?>

<div class="coco-thankyou-wrapper">
	<!-- TARJETA PRINCIPAL -->
	<div class="coco-thankyou-card">
		
		<!-- ENCABEZADO DE ÉXITO -->
		<div class="coco-thankyou-hero">
			<div class="coco-success-circle">
				<svg class="coco-checkmark-icon" viewBox="0 0 52 52">
					<circle class="coco-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
					<path class="coco-checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
				</svg>
			</div>

			<span class="coco-order-badge">
				<?php echo esc_html( sprintf( __( 'Pedido #%s registrado', 'coco-cod' ), $order_id ) ); ?>
			</span>

			<h1 class="coco-thankyou-title">
				<?php echo esc_html( sprintf( __( '¡Muchas gracias por tu compra, %s!', 'coco-cod' ), $first_name ? $first_name : '' ) ); ?>
			</h1>

			<p class="coco-thankyou-lead">
				<?php if ( $is_transfer ) : ?>
					<?php esc_html_e( 'Tu pedido ha sido registrado con éxito. Por favor realiza tu transferencia con los datos de abajo y envíanos el comprobante por WhatsApp para despachar tu paquete.', 'coco-cod' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Tu pedido ha sido recibido con éxito. En breve nuestro equipo se pondrá en contacto contigo vía WhatsApp o llamada para confirmar el despacho de tu paquete.', 'coco-cod' ); ?>
				<?php endif; ?>
			</p>
		</div>

		<!-- CONDICIÓN: TRANSFERENCIA vs PAGO EN EFECTIVO -->
		<?php if ( $is_transfer ) : ?>
			<!-- AVISO DESTACADO DE TRANSFERENCIA BANCARIA -->
			<div class="coco-bank-transfer-box">
				<div class="coco-bank-header">
					<div class="coco-bank-icon">🏦</div>
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
							<button type="button" class="coco-copy-btn" onclick="navigator.clipboard.writeText('<?php echo esc_js( $bank_account ); ?>'); this.innerText='¡Copiado!'; setTimeout(()=>{this.innerText='Copiar';}, 2000);">
								📋 <?php esc_html_e( 'Copiar', 'coco-cod' ); ?>
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
						📲 <strong><?php esc_html_e( 'Paso siguiente:', 'coco-cod' ); ?></strong>
						<?php esc_html_e( 'Una vez hecha la transferencia, por favor envía tu comprobante por WhatsApp indicando tu número de pedido para que despachemos tu paquete de inmediato.', 'coco-cod' ); ?>
					</p>
				</div>
			</div>
		<?php else : ?>
			<!-- AVISO DESTACADO DE PAGO EN EFECTIVO -->
			<div class="coco-payment-notice-box">
				<div class="coco-notice-icon">💵</div>
				<div class="coco-notice-content">
					<span class="coco-notice-tag"><?php esc_html_e( 'PAGO CONTRA ENTREGA (EN EFECTIVO)', 'coco-cod' ); ?></span>
					<div class="coco-notice-total">
						<span><?php esc_html_e( 'Monto exacto a pagar al recibir:', 'coco-cod' ); ?></span>
						<strong class="coco-total-highlight"><?php echo wp_kses_post( $total_formatted ); ?></strong>
					</div>
					<p class="coco-notice-tip">
						<?php esc_html_e( 'Por favor ten listo el dinero en efectivo cuando el repartidor llegue a tu dirección.', 'coco-cod' ); ?>
					</p>
				</div>
			</div>
		<?php endif; ?>

		<!-- LÍNEA DE TIEMPO / ESTADOS -->
		<div class="coco-steps-tracker">
			<div class="coco-tracker-step completed">
				<div class="coco-tracker-dot">✓</div>
				<span class="coco-tracker-text"><?php esc_html_e( 'Pedido Recibido', 'coco-cod' ); ?></span>
			</div>
			<div class="coco-tracker-line active"></div>
			<div class="coco-tracker-step current">
				<div class="coco-tracker-dot"><?php echo $is_transfer ? '💳' : '📞'; ?></div>
				<span class="coco-tracker-text"><?php echo $is_transfer ? esc_html__( 'Verificación Pago', 'coco-cod' ) : esc_html__( 'Coordinación', 'coco-cod' ); ?></span>
			</div>
			<div class="coco-tracker-line"></div>
			<div class="coco-tracker-step">
				<div class="coco-tracker-dot">🛵</div>
				<span class="coco-tracker-text"><?php esc_html_e( 'En Camino', 'coco-cod' ); ?></span>
			</div>
			<div class="coco-tracker-line"></div>
			<div class="coco-tracker-step">
				<div class="coco-tracker-dot">📦</div>
				<span class="coco-tracker-text"><?php esc_html_e( 'Entregado', 'coco-cod' ); ?></span>
			</div>
		</div>

		<div class="coco-thankyou-grid">
			<!-- COLUMNA 1: DATOS DE ENTREGA -->
			<div class="coco-section-card">
				<h3 class="coco-card-heading">
					<span class="coco-heading-icon">📍</span>
					<?php esc_html_e( 'Datos de Entrega', 'coco-cod' ); ?>
				</h3>
				
				<div class="coco-info-list">
					<div class="coco-info-item">
						<span class="coco-info-label"><?php esc_html_e( 'Destinatario:', 'coco-cod' ); ?></span>
						<strong class="coco-info-val"><?php echo esc_html( $full_name ); ?></strong>
					</div>

					<div class="coco-info-item">
						<span class="coco-info-label"><?php esc_html_e( 'Teléfono / WhatsApp:', 'coco-cod' ); ?></span>
						<strong class="coco-info-val"><?php echo esc_html( $phone ); ?></strong>
					</div>

					<?php if ( $email ) : ?>
						<div class="coco-info-item">
							<span class="coco-info-label"><?php esc_html_e( 'Correo electrónico:', 'coco-cod' ); ?></span>
							<span class="coco-info-val"><?php echo esc_html( $email ); ?></span>
						</div>
					<?php endif; ?>

					<div class="coco-info-item">
						<span class="coco-info-label"><?php esc_html_e( 'Ubicación:', 'coco-cod' ); ?></span>
						<span class="coco-info-val"><?php echo esc_html( $city . ', ' . $state_name ); ?></span>
					</div>

					<div class="coco-info-item">
						<span class="coco-info-label"><?php esc_html_e( 'Dirección:', 'coco-cod' ); ?></span>
						<span class="coco-info-val"><?php echo esc_html( $address_1 ); ?></span>
					</div>

					<?php if ( ! empty( $address_2 ) ) : ?>
						<div class="coco-info-item coco-ref-item">
							<span class="coco-info-label"><?php esc_html_e( 'Referencia:', 'coco-cod' ); ?></span>
							<span class="coco-info-val"><em><?php echo esc_html( $address_2 ); ?></em></span>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- COLUMNA 2: RESUMEN DE PRODUCTOS -->
			<div class="coco-section-card">
				<h3 class="coco-card-heading">
					<span class="coco-heading-icon">🛍️</span>
					<?php esc_html_e( 'Productos Comprados', 'coco-cod' ); ?>
				</h3>

				<div class="coco-items-list">
					<?php foreach ( $order->get_items() as $item_id => $item ) : ?>
						<?php 
							$product = $item->get_product(); 
							$image   = $product ? $product->get_image( array( 56, 56 ) ) : '';
						?>
						<div class="coco-thankyou-item">
							<?php if ( $image ) : ?>
								<div class="coco-item-thumb">
									<?php echo wp_kses_post( $image ); ?>
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
						<span><?php echo wp_kses_post( $shipping_formatted ); ?></span>
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
					<span class="coco-wa-icon">💬</span>
					<?php echo $is_transfer ? esc_html__( 'Enviar comprobante por WhatsApp', 'coco-cod' ) : esc_html__( 'Confirmar o Consultar por WhatsApp', 'coco-cod' ); ?>
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url( $shop_url ); ?>" class="coco-btn-return">
				&larr; <?php esc_html_e( 'Volver a la tienda', 'coco-cod' ); ?>
			</a>
		</div>

	</div>
</div>

<?php
/**
 * IMPORTANTE PARA MARKETING Y ANALÍTICA:
 * Mantenemos la ejecución del hook estándar de WooCommerce para que Facebook Pixel / Google Ads
 * disparen su evento Purchase con el ID del pedido y valor exacto.
 */
do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
do_action( 'woocommerce_thankyou', $order->get_id() );
?>
