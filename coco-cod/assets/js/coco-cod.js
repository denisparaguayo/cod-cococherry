/**
 * Lógica Frontend para Coco COD — Pedido Contra Entrega
 */
(function($) {
	'use strict';

	var CocoCOD = {
		currentStep: 1,
		shippingCost: 0,
		productPrice: 0,
		quantity: 1,
		variationId: 0,
		variationAttrs: {},

		init: function() {
			if (typeof cocoCodParams === 'undefined') {
				return;
			}

			this.productPrice = parseFloat(cocoCodParams.productPrice) || 0;
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			// Abrir modal desde la ficha de producto
			$(document).on('click', '#coco-cod-open-modal', function(e) {
				e.preventDefault();
				self.openModal();
			});

			// Cerrar modal
			$(document).on('click', '[data-coco-close]', function(e) {
				e.preventDefault();
				self.closeModal();
			});

			// Cerrar con tecla Escape
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $('#coco-cod-modal').hasClass('active')) {
					self.closeModal();
				}
			});

			// Cambio de departamento -> actualizar ciudades
			$(document).on('change', '#coco_state', function() {
				self.handleStateChange($(this).val());
			});

			// Cambio de ciudad -> calcular flete
			$(document).on('change', '#coco_city', function() {
				self.calculateShipping();
			});

			// Botón Paso 1 -> Paso 2
			$(document).on('click', '#coco-go-step-2', function(e) {
				e.preventDefault();
				self.validateStep1();
			});

			// Botón Paso 2 -> Paso 3
			$(document).on('click', '#coco-go-step-3', function(e) {
				e.preventDefault();
				self.validateStep2();
			});

			// Botones para volver de paso
			$(document).on('click', '[data-goto]', function(e) {
				e.preventDefault();
				var targetStep = parseInt($(this).data('goto'), 10);
				self.goToStep(targetStep);
			});

			// Envío final del pedido
			$(document).on('submit', '#coco-cod-order-form', function(e) {
				e.preventDefault();
				self.submitOrder();
			});
		},

		// Validar si es variable antes de abrir
		openModal: function() {
			var self = this;

			// Si es producto variable, comprobar que seleccionó las variaciones
			if (cocoCodParams.isVariable) {
				var $form = $('form.variations_form');
				var varId = parseInt($form.find('input[name="variation_id"]').val(), 10);

				if (!varId || varId === 0) {
					alert(cocoCodParams.i18n.selectVariation);
					$('html, body').animate({
						scrollTop: $form.offset().top - 100
					}, 300);
					return;
				}

				self.variationId = varId;
				$('#coco-form-variation-id').val(varId);

				// Guardar atributos de variación
				self.variationAttrs = {};
				$form.find('select[name^="attribute_"]').each(function() {
					var attrName = $(this).attr('name');
					self.variationAttrs[attrName] = $(this).val();
				});

				// Obtener precio de la variación si está disponible
				var currentVariation = $form.data('product_variations');
				if (currentVariation && currentVariation.length) {
					$.each(currentVariation, function(i, v) {
						if (v.variation_id === varId) {
							if (v.display_price) {
								self.productPrice = parseFloat(v.display_price);
							}
							if (v.image && v.image.thumb_src) {
								$('#coco-thumb-container img').attr('src', v.image.thumb_src);
							}
						}
					});
				}
			}

			// Obtener cantidad del selector de WooCommerce
			var qtyVal = parseInt($('form.cart input[name="quantity"]').val(), 10) || 1;
			self.quantity = qtyVal;
			$('#coco-form-qty').val(qtyVal);
			$('#coco-summary-qty').text(qtyVal);

			// Timestamps y UUID de seguridad
			$('#coco-rendered-at').val(Math.floor(Date.now() / 1000));
			$('#coco-idempotency-key').val(self.generateUUID());

			// Limpiar errores previos
			$('#coco-modal-errors').hide().empty();

			// Ir al Paso 1
			self.goToStep(1);

			// Mostrar modal
			$('#coco-cod-modal').addClass('active').attr('aria-hidden', 'false');
			$('body').css('overflow', 'hidden');

			// Foco en el primer campo
			setTimeout(function() {
				$('#coco_state').focus();
			}, 200);
		},

		closeModal: function() {
			$('#coco-cod-modal').removeClass('active').attr('aria-hidden', 'true');
			$('body').css('overflow', '');
		},

		handleStateChange: function(stateCode) {
			var $city = $('#coco_city');
			$city.empty();

			if (!stateCode || !cocoCodParams.locations[stateCode]) {
				$city.append('<option value="">-- Selecciona el departamento primero --</option>').prop('disabled', true);
				$('#coco-live-shipping').text('Selecciona tu ciudad');
				return;
			}

			var cities = cocoCodParams.locations[stateCode].cities;
			$city.append('<option value="">-- Selecciona tu Ciudad / Distrito --</option>');

			$.each(cities, function(i, city) {
				$city.append('<option value="' + city + '">' + city + '</option>');
			});

			$city.prop('disabled', false).focus();
			$('#coco-live-shipping').text('Selecciona tu ciudad');
		},

		calculateShipping: function() {
			var self = this;
			var state = $('#coco_state').val();
			var city = $('#coco_city').val();

			if (!state || !city) {
				return;
			}

			$('#coco-live-shipping').html('<i>' + cocoCodParams.i18n.calcShipping + '</i>');

			$.ajax({
				url: cocoCodParams.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'coco_cod_shipping',
					security: cocoCodParams.nonce,
					product_id: cocoCodParams.productId,
					variation_id: self.variationId,
					qty: self.quantity,
					state: state,
					city: city
				},
				success: function(res) {
					if (res.success) {
						self.shippingCost = res.data.shipping_cost;
						$('#coco-live-shipping').html(res.data.formatted_shipping);
						$('#coco-final-subtotal').html(res.data.formatted_subtotal);
						$('#coco-final-shipping').html(res.data.formatted_shipping);
						$('#coco-final-total').html(res.data.formatted_total);

						var labelPrice = res.data.plain_total ? res.data.plain_total : (res.data.total ? res.data.total + ' ' + cocoCodParams.currencySymbol : '');
						$('#coco-btn-submit-order .coco-submit-label').text(cocoCodParams.i18n.confirmOrder + (labelPrice ? ' — ' + labelPrice : ''));
					} else {
						$('#coco-live-shipping').text('No se pudo calcular');
					}
				},
				error: function() {
					$('#coco-live-shipping').text('Error de conexión');
				}
			});
		},

		validateStep1: function() {
			var state = $('#coco_state').val();
			var city = $('#coco_city').val();
			var addr = $.trim($('#coco_address_1').val());

			if (!state || !city || addr.length < 4) {
				this.showError('Por favor selecciona tu departamento, ciudad e ingresa tu dirección completa.');
				return;
			}

			this.hideError();
			this.goToStep(2);
		},

		validateStep2: function() {
			var fName = $.trim($('#coco_first_name').val());
			var lName = $.trim($('#coco_last_name').val());
			var phone = $.trim($('#coco_phone').val());
			var email = $.trim($('#coco_email').val());

			if (fName.length < 2 || lName.length < 2) {
				this.showError('Por favor completa tu nombre y apellido.');
				return;
			}

			// Validar teléfono mínimo 8 dígitos
			var cleanPhone = phone.replace(/\D/g, '');
			if (cleanPhone.length < 8) {
				this.showError(cocoCodParams.i18n.invalidPhone);
				return;
			}

			// Validar email obligatorio y con formato real
			var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if (!email || !emailPattern.test(email)) {
				this.showError(cocoCodParams.i18n.invalidEmail);
				return;
			}

			this.hideError();
			this.updateSummaryCard();
			this.goToStep(3);
		},

		updateSummaryCard: function() {
			var stateName = $('#coco_state option:selected').text();
			var city = $('#coco_city').val();
			var addr1 = $('#coco_address_1').val();
			var addr2 = $('#coco_address_2').val();
			var fName = $('#coco_first_name').val();
			var lName = $('#coco_last_name').val();
			var phone = $('#coco_phone').val();
			var email = $('#coco_email').val();

			$('#coco-summary-dest').text(city + ', ' + stateName);
			$('#coco-summary-addr').text(addr1 + (addr2 ? ' (Ref: ' + addr2 + ')' : ''));
			$('#coco-summary-client').text(fName + ' ' + lName);
			$('#coco-summary-contact').text(phone + ' · ' + email);
		},

		goToStep: function(stepNum) {
			this.currentStep = stepNum;

			// Actualizar barra de pasos
			$('.coco-step-item').removeClass('active');
			$('.coco-step-item[data-step="' + stepNum + '"]').addClass('active');

			// Actualizar paneles visibles
			$('.coco-step-pane').removeClass('active');
			$('#coco-step-' + stepNum).addClass('active');

			// Scroll al inicio del modal
			$('.coco-cod-dialog').scrollTop(0);
		},

		submitOrder: function() {
			var self = this;
			var $btn = $('#coco-btn-submit-order');

			$btn.prop('disabled', true).addClass('loading');
			$btn.find('.coco-submit-label').hide();
			$btn.find('.coco-submit-spinner').show();
			self.hideError();

			var formData = $('#coco-cod-order-form').serializeArray();
			formData.push({ name: 'action', value: 'coco_cod_order' });
			formData.push({ name: 'security', value: cocoCodParams.nonce });

			// Incluir atributos de variación
			if (self.variationAttrs) {
				$.each(self.variationAttrs, function(k, v) {
					formData.push({ name: 'variation_attrs[' + k + ']', value: v });
				});
			}

			$.ajax({
				url: cocoCodParams.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: formData,
				success: function(res) {
					// WooCommerce process_checkout devuelve { result: 'success', redirect: '...' }
					if (res && res.result === 'success' && res.redirect) {
						window.location.href = res.redirect;
						return;
					}

					// Si WooCommerce retornó failure
					if (res && res.result === 'failure') {
						var msg = res.messages ? res.messages : cocoCodParams.i18n.errorOrder;
						self.showError(msg);
						$btn.prop('disabled', false).removeClass('loading');
						$btn.find('.coco-submit-label').show();
						$btn.find('.coco-submit-spinner').hide();
						return;
					}

					// Si viene por wp_send_json_error
					if (res && !res.success) {
						var errMsg = res.data && res.data.message ? res.data.message : cocoCodParams.i18n.errorOrder;
						self.showError(errMsg);
						$btn.prop('disabled', false).removeClass('loading');
						$btn.find('.coco-submit-label').show();
						$btn.find('.coco-submit-spinner').hide();
						return;
					}

					// Fallback
					window.location.reload();
				},
				error: function() {
					self.showError(cocoCodParams.i18n.errorOrder);
					$btn.prop('disabled', false).removeClass('loading');
					$btn.find('.coco-submit-label').show();
					$btn.find('.coco-submit-spinner').hide();
				}
			});
		},

		showError: function(msg) {
			$('#coco-modal-errors').html(msg).show();
			$('.coco-cod-dialog').animate({ scrollTop: 0 }, 200);
		},

		hideError: function() {
			$('#coco-modal-errors').hide().empty();
		},

		generateUUID: function() {
			return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
				var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
				return v.toString(16);
			});
		}
	};

	$(document).ready(function() {
		CocoCOD.init();
	});

})(jQuery);
