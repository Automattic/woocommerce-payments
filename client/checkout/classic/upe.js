/* global jQuery */

/**
 * Internal dependencies
 */
import './style.scss';
import {
	PAYMENT_METHOD_NAME_CARD,
	PAYMENT_METHOD_NAME_UPE,
} from '../constants.js';
import { getConfig, getCustomGatewayTitle } from 'utils/checkout';
import WCPayAPI from '../api';
import enqueueFraudScripts from 'fraud-scripts';
import { getFontRulesFromPage, getAppearance } from '../upe-styles';
import { getTerms } from '../utils/upe';

jQuery( function ( $ ) {
	enqueueFraudScripts( getConfig( 'fraudServices' ) );

	const publishableKey = getConfig( 'publishableKey' );
	const isChangingPayment = getConfig( 'isChangingPayment' );
	const isUPEEnabled = getConfig( 'isUPEEnabled' );
	const paymentMethodsConfig = getConfig( 'paymentMethodsConfig' );
	const enabledBillingFields = getConfig( 'enabledBillingFields' );

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	// Create an API object, which will be used throughout the checkout.
	const api = new WCPayAPI(
		{
			publishableKey,
			accountId: getConfig( 'accountId' ),
			forceNetworkSavedCards: getConfig( 'forceNetworkSavedCards' ),
			locale: getConfig( 'locale' ),
			isUPEEnabled,
		},
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);

	// Object to add hidden elements to compute focus and invalid states for UPE.
	const hiddenElementsForUPE = {
		getHiddenContainer: function () {
			const hiddenDiv = document.createElement( 'div' );
			hiddenDiv.setAttribute( 'id', 'wcpay-hidden-div' );
			hiddenDiv.style.border = 0;
			hiddenDiv.style.clip = 'rect(0 0 0 0)';
			hiddenDiv.style.height = '1px';
			hiddenDiv.style.margin = '-1px';
			hiddenDiv.style.overflow = 'hidden';
			hiddenDiv.style.padding = '0';
			hiddenDiv.style.position = 'absolute';
			hiddenDiv.style.width = '1px';
			return hiddenDiv;
		},
		getHiddenInvalidRow: function () {
			const hiddenInvalidRow = document.createElement( 'p' );
			hiddenInvalidRow.classList.add(
				'form-row',
				'woocommerce-invalid',
				'woocommerce-invalid-required-field'
			);
			return hiddenInvalidRow;
		},
		appendHiddenClone: function ( container, idToClone, hiddenCloneId ) {
			const hiddenInput = jQuery( idToClone )
				.clone()
				.prop( 'id', hiddenCloneId );
			container.appendChild( hiddenInput.get( 0 ) );
			return hiddenInput;
		},
		init: function () {
			if ( ! $( ' #billing_first_name' ).length ) {
				return;
			}
			const hiddenDiv = this.getHiddenContainer();

			// // Hidden focusable element.
			$( hiddenDiv ).insertAfter( '#billing_first_name' );
			this.appendHiddenClone(
				hiddenDiv,
				'#billing_first_name',
				'wcpay-hidden-input'
			);

			// Hidden invalid element.
			const hiddenInvalidRow = this.getHiddenInvalidRow();
			this.appendHiddenClone(
				hiddenInvalidRow,
				'#billing_first_name',
				'wcpay-hidden-invalid-input'
			);
			hiddenDiv.appendChild( hiddenInvalidRow );

			// Remove transitions.
			$( '#wcpay-hidden-input' ).css( 'transition', 'none' );
			$( '#wcpay-hidden-input' ).trigger( 'focus' );
		},
		cleanup: function () {
			$( '#wcpay-hidden-div' ).remove();
		},
	};

	const elements = api.getStripe().elements( {
		fonts: getFontRulesFromPage(),
	} );

	let upeElement = null;
	let paymentIntentId = null;
	let isUPEComplete = false;
	const hiddenBillingFields = {
		name:
			enabledBillingFields.includes( 'billing_first_name' ) ||
			enabledBillingFields.includes( 'billing_last_name' )
				? 'never'
				: 'auto',
		email: enabledBillingFields.includes( 'billing_email' )
			? 'never'
			: 'auto',
		phone: enabledBillingFields.includes( 'billing_phone' )
			? 'never'
			: 'auto',
		address: {
			country: enabledBillingFields.includes( 'billing_country' )
				? 'never'
				: 'auto',
			line1: enabledBillingFields.includes( 'billing_address_1' )
				? 'never'
				: 'auto',
			line2: enabledBillingFields.includes( 'billing_address_2' )
				? 'never'
				: 'auto',
			city: enabledBillingFields.includes( 'billing_city' )
				? 'never'
				: 'auto',
			state: enabledBillingFields.includes( 'billing_state' )
				? 'never'
				: 'auto',
			postalCode: enabledBillingFields.includes( 'billing_postcode' )
				? 'never'
				: 'auto',
		},
	};

	/**
	 * Block UI to indicate processing and avoid duplicate submission.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 */
	const blockUI = ( $form ) => {
		$form.addClass( 'processing' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6,
			},
		} );
	};

	/**
	 * Unblock UI to remove overlay and loading icon
	 *
	 * @param {Object} $form The jQuery object for the form.
	 */
	const unblockUI = ( $form ) => {
		$form.removeClass( 'processing' ).unblock();
	};

	// Show error notice at top of checkout form.
	const showError = ( errorMessage ) => {
		let messageWrapper = '';
		if ( errorMessage.includes( 'woocommerce-error' ) ) {
			messageWrapper = errorMessage;
		} else {
			messageWrapper =
				'<ul class="woocommerce-error" role="alert">' +
				errorMessage +
				'</ul>';
		}
		const $container = $(
			'.woocommerce-notices-wrapper, form.checkout'
		).first();

		if ( ! $container.length ) {
			return;
		}

		// Adapted from WooCommerce core @ ea9aa8c, assets/js/frontend/checkout.js#L514-L529
		$(
			'.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message'
		).remove();
		$container.prepend(
			'<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
				messageWrapper +
				'</div>'
		);
		$container
			.find( '.input-text, select, input:checkbox' )
			.trigger( 'validate' )
			.blur();

		let scrollElement = $( '.woocommerce-NoticeGroup-checkout' );
		if ( ! scrollElement.length ) {
			scrollElement = $container;
		}

		$.scroll_to_notices( scrollElement );
		$( document.body ).trigger( 'checkout_error' );
	};

	// Show or hide save payment information checkbox
	const showNewPaymentMethodCheckbox = ( show = true ) => {
		if ( show ) {
			$( '.woocommerce-SavedPaymentMethods-saveNew' ).show();
		} else {
			$( '.woocommerce-SavedPaymentMethods-saveNew' ).hide();
			$( 'input#wc-woocommerce_payments-new-payment-method' ).prop(
				'checked',
				false
			);
			$( 'input#wc-woocommerce_payments-new-payment-method' ).trigger(
				'change'
			);
		}
	};

	// Set the selected UPE payment type field
	const setSelectedUPEPaymentType = ( paymentType ) => {
		$( '#wcpay_selected_upe_payment_type' ).val( paymentType );
	};

	// Set the payment country field
	const setPaymentCountry = ( country ) => {
		$( '#wcpay_payment_country' ).val( country );
	};

	/**
	 * Converts form fields object into Stripe `billing_details` object.
	 *
	 * @param {Object} fields Object mapping checkout billing fields to values.
	 * @return {Object} Stripe formatted `billing_details` object.
	 */
	const getBillingDetails = ( fields ) => {
		return {
			name:
				`${ fields.billing_first_name } ${ fields.billing_last_name }`.trim() ||
				'-',
			email: fields.billing_email || '-',
			phone: fields.billing_phone || '-',
			address: {
				country: fields.billing_country || '-',
				line1: fields.billing_address_1 || '-',
				line2: fields.billing_address_2 || '-',
				city: fields.billing_city || '-',
				state: fields.billing_state || '-',
				postal_code: fields.billing_postcode || '-',
			},
		};
	};

	/**
	 * Mounts Stripe UPE element if feature is enabled.
	 *
	 * @param {boolean} isSetupIntent {Boolean} isSetupIntent Set to true if we are on My Account adding a payment method.
	 */
	const mountUPEElement = function ( isSetupIntent = false ) {
		// Do not mount UPE twice.
		if ( upeElement || paymentIntentId ) {
			return;
		}

		/*
		 * Trigger this event to ensure the tokenization-form.js init
		 * is executed.
		 *
		 * This script handles the radio input interaction when toggling
		 * between the user's saved card / entering new card details.
		 *
		 * Ref: https://github.com/woocommerce/woocommerce/blob/2429498/assets/js/frontend/tokenization-form.js#L109
		 */
		$( document.body ).trigger( 'wc-credit-card-form-init' );

		// If paying from order, we need to create Payment Intent from order not cart.
		const isOrderPay = getConfig( 'isOrderPay' );
		const isCheckout = getConfig( 'isCheckout' );
		let orderId;
		if ( isOrderPay ) {
			orderId = getConfig( 'orderId' );
		}

		const intentAction = isSetupIntent
			? api.initSetupIntent()
			: api.createIntent( orderId );

		const $upeContainer = $( '#wcpay-upe-element' );
		blockUI( $upeContainer );

		intentAction
			.then( ( response ) => {
				// I repeat, do NOT mount UPE twice.
				if ( upeElement || paymentIntentId ) {
					unblockUI( $upeContainer );
					return;
				}

				const { client_secret: clientSecret, id: id } = response;
				paymentIntentId = id;

				let appearance = getConfig( 'upeAppearance' );

				if ( ! appearance ) {
					hiddenElementsForUPE.init();
					appearance = getAppearance();
					hiddenElementsForUPE.cleanup();
					api.saveUPEAppearance( appearance );
				}

				const upeSettings = {
					clientSecret,
					appearance,
				};

				if ( getConfig( 'cartContainsSubscription' ) ) {
					upeSettings.terms = getTerms(
						paymentMethodsConfig,
						'always'
					);
				}
				if ( isCheckout && ! ( isOrderPay || isChangingPayment ) ) {
					upeSettings.fields = {
						billingDetails: hiddenBillingFields,
					};
				}

				upeElement = elements.create( 'payment', upeSettings );
				upeElement.mount( '#wcpay-upe-element' );
				unblockUI( $upeContainer );
				upeElement.on( 'change', ( event ) => {
					const selectedUPEPaymentType = event.value.type;
					const isPaymentMethodReusable =
						paymentMethodsConfig[ selectedUPEPaymentType ]
							.isReusable;
					showNewPaymentMethodCheckbox( isPaymentMethodReusable );
					setSelectedUPEPaymentType( selectedUPEPaymentType );
					setPaymentCountry( event.value.country );
					isUPEComplete = event.complete;
				} );
			} )
			.catch( ( error ) => {
				unblockUI( $upeContainer );
				showError( error.message );
				const gatewayErrorMessage =
					'<div>An error was encountered when preparing the payment form. Please try again later.</div>';
				$( '.payment_box.payment_method_woocommerce_payments' ).html(
					gatewayErrorMessage
				);
			} );
	};

	const renameGatewayTitle = () =>
		$( 'label[for=payment_method_woocommerce_payments]' ).text(
			getCustomGatewayTitle( paymentMethodsConfig )
		);

	// Only attempt to mount the card element once that section of the page has loaded. We can use the updated_checkout
	// event for this. This part of the page can also reload based on changes to checkout details, so we call unmount
	// first to ensure the card element is re-mounted correctly.
	$( document.body ).on( 'updated_checkout', () => {
		// If the card element selector doesn't exist, then do nothing (for example, when a 100% discount coupon is applied).
		// We also don't re-mount if already mounted in DOM.
		if (
			$( '#wcpay-upe-element' ).length &&
			! $( '#wcpay-upe-element' ).children().length &&
			isUPEEnabled &&
			! upeElement
		) {
			renameGatewayTitle();
			mountUPEElement();
		}
	} );

	if (
		$( 'form#add_payment_method' ).length ||
		$( 'form#order_review' ).length
	) {
		if (
			$( '#wcpay-upe-element' ).length &&
			! $( '#wcpay-upe-element' ).children().length &&
			isUPEEnabled &&
			! upeElement
		) {
			renameGatewayTitle();

			// We use a setup intent if we are on the screens to add a new payment method or to change a subscription payment.
			const useSetUpIntent =
				$( 'form#add_payment_method' ).length || isChangingPayment;

			if ( isChangingPayment && getConfig( 'newTokenFormId' ) ) {
				// Changing the method for a subscription takes two steps:
				// 1. Create the new payment method that will redirect back.
				// 2. Select the new payment method and resubmit the form to update the subscription.
				const token = getConfig( 'newTokenFormId' );
				$( token ).prop( 'selected', true ).trigger( 'click' );
				$( 'form#order_review' ).submit();
			}
			mountUPEElement( useSetUpIntent );
		}
	}

	/**
	 * Checks if UPE form is filled out. Displays errors if not.
	 *
	 * @param {Object} $form     The jQuery object for the form.
	 * @param {string} returnUrl The `return_url` param. (optional)
	 * @return {boolean} false if incomplete.
	 */
	const checkUPEForm = async ( $form, returnUrl = '' ) => {
		if ( ! upeElement ) {
			showError( 'Your payment information is incomplete.' );
			return false;
		}
		if ( ! isUPEComplete ) {
			// If UPE fields are not filled, confirm payment to trigger validation errors
			const { error } = await api.getStripe().confirmPayment( {
				element: upeElement,
				confirmParams: {
					return_url: returnUrl,
				},
			} );
			$form.removeClass( 'processing' ).unblock();
			showError( error.message );
			return false;
		}
		return true;
	};
	/**
	 * Submits the confirmation of the intent to Stripe on Pay for Order page.
	 * Stripe redirects to Order Thank you page on sucess.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 * @return {boolean} A flag for the event handler.
	 */
	const handleUPEOrderPay = async ( $form ) => {
		const isSavingPaymentMethod = $(
			'#wc-woocommerce_payments-new-payment-method'
		).is( ':checked' );
		const savePaymentMethod = isSavingPaymentMethod ? 'yes' : 'no';

		const returnUrl =
			getConfig( 'orderReturnURL' ) +
			`&save_payment_method=${ savePaymentMethod }`;

		const orderId = getConfig( 'orderId' );

		const isUPEFormValid = await checkUPEForm(
			$( '#order_review' ),
			returnUrl
		);
		if ( ! isUPEFormValid ) {
			return;
		}
		blockUI( $form );

		try {
			// Update payment intent with level3 data, customer and maybe setup for future use.
			await api.updateIntent(
				paymentIntentId,
				orderId,
				savePaymentMethod,
				$( '#wcpay_selected_upe_payment_type' ).val(),
				$( '#wcpay_payment_country' ).val()
			);

			const { error } = await api.getStripe().confirmPayment( {
				element: upeElement,
				confirmParams: {
					return_url: returnUrl,
				},
			} );
			if ( error ) {
				throw error;
			}
		} catch ( error ) {
			$form.removeClass( 'processing' ).unblock();
			showError( error.message );
		}
	};

	/**
	 * Submits the confirmation of the setup intent to Stripe on Add Payment Method page.
	 * Stripe redirects to Payment Methods page on sucess.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 * @return {boolean} A flag for the event handler.
	 */
	const handleUPEAddPayment = async ( $form ) => {
		const returnUrl = getConfig( 'addPaymentReturnURL' );
		const isUPEFormValid = await checkUPEForm( $form, returnUrl );

		if ( ! isUPEFormValid ) {
			return;
		}

		blockUI( $form );

		try {
			const { error } = await api.getStripe().confirmSetup( {
				element: upeElement,
				confirmParams: {
					return_url: returnUrl,
				},
			} );
			if ( error ) {
				throw error;
			}
		} catch ( error ) {
			$form.removeClass( 'processing' ).unblock();
			showError( error.message );
		}
	};

	/**
	 * Submits checkout form via AJAX to create order and uses custom
	 * redirect URL in AJAX response to request payment confirmation from UPE
	 *
	 * @param {Object} $form The jQuery object for the form.
	 * @return {boolean} A flag for the event handler.
	 */
	const handleUPECheckout = async ( $form ) => {
		const isUPEFormValid = await checkUPEForm( $form );
		if ( ! isUPEFormValid ) {
			return;
		}

		blockUI( $form );
		// Create object where keys are form field names and keys are form field values
		const formFields = $form.serializeArray().reduce( ( obj, field ) => {
			obj[ field.name ] = field.value;
			return obj;
		}, {} );
		try {
			const response = await api.processCheckout(
				paymentIntentId,
				formFields
			);
			const redirectUrl = response.redirect_url;
			const upeConfig = {
				element: upeElement,
				confirmParams: {
					return_url: redirectUrl,
					payment_method_data: {
						billing_details: getBillingDetails( formFields ),
					},
				},
			};
			let error;
			if ( response.payment_needed ) {
				( { error } = await api
					.getStripe()
					.confirmPayment( upeConfig ) );
			} else {
				( { error } = await api.getStripe().confirmSetup( upeConfig ) );
			}
			if ( error ) {
				// Log payment errors on charge and then throw the error.
				const logError = await api.logPaymentError( error.charge );
				if ( logError ) {
					throw error;
				}
			}
		} catch ( error ) {
			$form.removeClass( 'processing' ).unblock();
			showError( error.message );
		}
	};

	/**
	 * Displays the authentication modal to the user if needed.
	 */
	const maybeShowAuthenticationModal = () => {
		const paymentMethodId = $( '#wcpay-payment-method' ).val();

		const savePaymentMethod = $(
			'#wc-woocommerce_payments-new-payment-method'
		).is( ':checked' );
		const confirmation = api.confirmIntent(
			window.location.href,
			savePaymentMethod ? paymentMethodId : null
		);

		// Boolean `true` means that there is nothing to confirm.
		if ( true === confirmation ) {
			return;
		}

		const { request, isOrderPage } = confirmation;

		if ( isOrderPage ) {
			blockUI( $( '#order_review' ) );
			$( '#payment' ).hide( 500 );
		}

		// Cleanup the URL.
		// https://stackoverflow.com/a/5298684
		history.replaceState(
			'',
			document.title,
			window.location.pathname + window.location.search
		);

		request
			.then( ( redirectUrl ) => {
				window.location = redirectUrl;
			} )
			.catch( ( error ) => {
				$( 'form.checkout' ).removeClass( 'processing' ).unblock();
				$( '#order_review' ).removeClass( 'processing' ).unblock();
				$( '#payment' ).show( 500 );

				let errorMessage = error.message;

				// If this is a generic error, we probably don't want to display the error message to the user,
				// so display a generic message instead.
				if ( error instanceof Error ) {
					errorMessage = getConfig( 'genericErrorMessage' );
				}

				showError( errorMessage );
			} );
	};

	/**
	 * Checks if the customer is using a saved payment method.
	 *
	 * @return {boolean} Boolean indicating whether or not a saved payment method is being used.
	 */
	function isUsingSavedPaymentMethod() {
		return (
			$( '#wc-woocommerce_payments-payment-token-new' ).length &&
			! $( '#wc-woocommerce_payments-payment-token-new' ).is( ':checked' )
		);
	}

	// Handle the checkout form when WooCommerce Payments is chosen.
	const wcpayPaymentMethods = [
		PAYMENT_METHOD_NAME_CARD,
		PAYMENT_METHOD_NAME_UPE,
	];
	const checkoutEvents = wcpayPaymentMethods
		.map( ( method ) => `checkout_place_order_${ method }` )
		.join( ' ' );
	$( 'form.checkout' ).on( checkoutEvents, function () {
		if ( ! isUsingSavedPaymentMethod() ) {
			if ( isUPEEnabled && paymentIntentId ) {
				handleUPECheckout( $( this ) );
				return false;
			}
		}
	} );

	// Handle the add payment method form for WooCommerce Payments.
	$( 'form#add_payment_method' ).on( 'submit', function () {
		if (
			'woocommerce_payments' !==
			$(
				"#add_payment_method input:checked[name='payment_method']"
			).val()
		) {
			return;
		}

		if ( ! $( '#wcpay-setup-intent' ).val() ) {
			if ( isUPEEnabled && paymentIntentId ) {
				handleUPEAddPayment( $( this ) );
				return false;
			}
		}
	} );

	// Handle the Pay for Order form if WooCommerce Payments is chosen.
	$( '#order_review' ).on( 'submit', () => {
		if ( ! isUsingSavedPaymentMethod() ) {
			if ( isChangingPayment ) {
				handleUPEAddPayment( $( '#order_review' ) );
				return false;
			}
			handleUPEOrderPay( $( '#order_review' ) );
			return false;
		}
	} );

	// Add terms parameter to UPE if save payment information checkbox is checked.
	$( document ).on(
		'change',
		'#wc-woocommerce_payments-new-payment-method',
		() => {
			const value = $( '#wc-woocommerce_payments-new-payment-method' ).is(
				':checked'
			)
				? 'always'
				: 'never';
			if ( isUPEEnabled && upeElement ) {
				upeElement.update( {
					terms: getTerms( paymentMethodsConfig, value ),
				} );
			}
		}
	);

	// On every page load, check to see whether we should display the authentication
	// modal and display it if it should be displayed.
	maybeShowAuthenticationModal();

	// Handle hash change - used when authenticating payment with SCA on checkout page.
	window.addEventListener( 'hashchange', () => {
		if ( window.location.hash.startsWith( '#wcpay-confirm-' ) ) {
			maybeShowAuthenticationModal();
		}
	} );
} );
