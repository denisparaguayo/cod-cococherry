<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plantilla del botón "Pagar al recibir" en la ficha de producto.
 */
?>
<div class="coco-cod-btn-wrapper">
	<button type="button" id="coco-cod-open-modal" class="coco-cod-button" aria-haspopup="dialog" aria-controls="coco-cod-modal">
		<span class="coco-cod-btn-icon">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<rect x="1" y="3" width="15" height="13"></rect>
				<polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
				<circle cx="5.5" cy="18.5" r="2.5"></circle>
				<circle cx="18.5" cy="18.5" r="2.5"></circle>
			</svg>
		</span>
		<span class="coco-cod-btn-content">
			<span class="coco-cod-btn-title"><?php echo esc_html( $btn_text ); ?></span>
			<?php if ( ! empty( $btn_subtitle ) ) : ?>
				<span class="coco-cod-btn-desc"><?php echo esc_html( $btn_subtitle ); ?></span>
			<?php endif; ?>
		</span>
	</button>
</div>
