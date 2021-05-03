/* global jQuery */

/**
 * Internal dependencies
 */
import './style.scss';
import {
	PAYMENT_METHOD_NAME_CARD,
	PAYMENT_METHOD_NAME_GIROPAY,
	PAYMENT_METHOD_NAME_SEPA,
	PAYMENT_METHOD_NAME_SOFORT,
} from '../constants.js';
import { getConfig } from 'utils/checkout';
import WCPayAPI from './../api';
import enqueueFraudScripts from 'fraud-scripts';
import { supportedUPEProperties } from '../upe-styles.js';

jQuery( function ( $ ) {
	enqueueFraudScripts( getConfig( 'fraudServices' ) );

	const publishableKey = getConfig( 'publishableKey' );
	const upeColor = getConfig( 'upeColor' );
	const upeBorder = getConfig( 'upeBorder' );
	const upeFontSource = getConfig( 'upeFontSource' );
	const upeFontFamily = getConfig( 'upeFontFamily' );
	const upeThemeInputSelector = getConfig( 'upeThemeInputSelector' );
	const upeThemeLabelSelector = getConfig( 'upeThemeLabelSelector' );

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
		},
		// A promise-based interface to jQuery.post.
		( url, args ) => {
			return new Promise( ( resolve, reject ) => {
				jQuery.post( url, args ).then( resolve ).fail( reject );
			} );
		}
	);
	const elements = api.getStripe().elements( {
		fonts: [
			{
				cssSrc: upeFontSource,
			},
		],
	} );

	// Customer information for Pay for Order and Save Payment method.
	/* global wcpayCustomerData */
	const preparedCustomerData =
		'undefined' !== typeof wcpayCustomerData ? wcpayCustomerData : {};

	// Create a card element.
	const cardElement = elements.create( 'card', {
		hidePostalCode: true,
		classes: { base: 'wcpay-card-mounted' },
	} );

	const cardPayment = {
		type: 'card',
		card: cardElement,
	};

	// Giropay payment method details
	const giropayPayment = {
		type: 'giropay' /* eslint-disable camelcase */,
	};

	// Create a SEPA element
	const sepaElement = elements.create( 'iban', {
		// 'SEPA' Indicates all countries in the Single Euro Payments Area (SEPA).
		supportedCountries: [ 'SEPA' ],
		classes: { base: 'wcpay-sepa-mounted' },
	} );

	const sepaPayment = {
		type: 'sepa_debit' /* eslint-disable camelcase */,
		sepa_debit: sepaElement,
	};

	// Sofort payment method details
	const sofortPayment = {
		type: 'sofort' /* eslint-disable camelcase */,
	};

	/**
	 * Check if Card payment is being used.
	 *
	 * @return {boolean} Boolean indicating whether or not Card payment is being used.
	 */
	const isWCPayCardChosen = function () {
		return $( '#payment_method_woocommerce_payments' ).is( ':checked' );
	};

	/**
	 * Check if Giropay payment is being used.
	 *
	 * @return {boolean} Boolean indicating whether or not Giropay payment is being used.
	 */
	const isWCPayGiropayChosen = function () {
		return $( '#payment_method_woocommerce_payments_giropay' ).is(
			':checked'
		);
	};

	/**
	 * Check if SEPA Direct Debit is being used.
	 *
	 * @return {boolean} Boolean indicating whether or not SEPA Direct Debit is being used.
	 */
	const isWCPaySepaChosen = function () {
		return $( '#payment_method_woocommerce_payments_sepa' ).is(
			':checked'
		);
	};

	/**
	 * Check if Sofort payment method is being used.
	 *
	 * @return {boolean} Boolean indicating whether or not Sofort payment method is being used.
	 */
	const isWCPaySofortChosen = function () {
		return $( '#payment_method_woocommerce_payments_sofort' ).is(
			':checked'
		);
	};

	// Only attempt to mount the card element once that section of the page has loaded. We can use the updated_checkout
	// event for this. This part of the page can also reload based on changes to checkout details, so we call unmount
	// first to ensure the card element is re-mounted correctly.
	$( document.body ).on( 'updated_checkout', () => {
		// If the card element selector doesn't exist, then do nothing (for example, when a 100% discount coupon is applied).
		// We also don't re-mount if already mounted in DOM.
		if (
			! $( '#wcpay-card-element' ).length ||
			$( '#wcpay-card-element' ).children().length
		) {
			return;
		}

		cardElement.unmount();
		cardElement.mount( '#wcpay-card-element' );

		if ( $( '#wcpay-sepa-element' ).length ) {
			sepaElement.mount( '#wcpay-sepa-element' );
		}
	} );

	if (
		$( 'form#add_payment_method' ).length ||
		$( 'form#order_review' ).length
	) {
		cardElement.mount( '#wcpay-card-element' );

		if ( $( '#wcpay-sepa-element' ).length ) {
			sepaElement.mount( '#wcpay-sepa-element' );
		}
	}

	// Update the validation state based on the element's state.
	cardElement.addEventListener( 'change', ( event ) => {
		const displayError = $( '#wcpay-errors' );
		if ( event.error ) {
			displayError
				.html( '<ul class="woocommerce-error"><li /></ul>' )
				.find( 'li' )
				.text( event.error.message );
		} else {
			displayError.empty();
		}
	} );

	sepaElement.addEventListener( 'change', ( event ) => {
		const displayError = $( '#wcpay-sepa-errors' );
		if ( event.error ) {
			displayError
				.html( '<ul class="woocommerce-error"><li /></ul>' )
				.find( 'li' )
				.text( event.error.message );
		} else {
			displayError.empty();
		}
	} );

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

	// Show error notice at top of checkout form.
	const showError = ( errorMessage ) => {
		const messageWrapper =
			'<ul class="woocommerce-error" role="alert">' +
			errorMessage +
			'</ul>';
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

	// Create payment method on submission.
	let paymentMethodGenerated;

	/**
	 * Creates and authorizes a setup intent, saves its ID in a hidden input, and re-submits the form.
	 *
	 * @param {Object} $form         The jQuery object for the form.
	 * @param {Object} paymentMethod Payment method object.
	 */
	const handleAddCard = ( $form, paymentMethod ) => {
		api.setupIntent( paymentMethod.id )
			.then( function ( confirmedSetupIntent ) {
				// Populate form with the setup intent and re-submit.
				$form.append(
					$( '<input type="hidden" />' )
						.attr( 'id', 'wcpay-setup-intent' )
						.attr( 'name', 'wcpay-setup-intent' )
						.val( confirmedSetupIntent.id )
				);

				// WC core calls block() when add_payment_form is submitted, so we need to enable the ignore flag here to avoid
				// the overlay blink when the form is blocked twice. We can restore its default value once the form is submitted.
				const defaultIgnoreIfBlocked =
					$.blockUI.defaults.ignoreIfBlocked;
				$.blockUI.defaults.ignoreIfBlocked = true;

				// Re-submit the form.
				$form.removeClass( 'processing' ).submit();

				// Restore default value for ignoreIfBlocked.
				$.blockUI.defaults.ignoreIfBlocked = defaultIgnoreIfBlocked;
			} )
			.catch( function ( error ) {
				paymentMethodGenerated = null;
				$form.removeClass( 'processing' ).unblock();
				showError( error.message );
			} );
	};

	/**
	 * Saves the payment method ID in a hidden input, and re-submits the form.
	 *
	 * @param {Object} $form         The jQuery object for the form.
	 * @param {Object} paymentMethod Payment method object.
	 */
	const handleOrderPayment = ( $form, { id } ) => {
		const paymentSelector = isWCPaySepaChosen()
			? '#wcpay-payment-method-sepa'
			: '#wcpay-payment-method';

		// Populate form with the payment method.
		$( paymentSelector ).val( id );

		// Re-submit the form.
		$form.removeClass( 'processing' ).submit();
	};

	/**
	 * Generates a payment method, saves its ID in a hidden input, and re-submits the form.
	 *
	 * @param {Object} $form The jQuery object for the form.
	 * @param {Function} successHandler    Callback to be executed when payment method is generated.
	 * @param {Object}  paymentMethodDetails { type: 'card' | 'sepa_debit', card? | sepa_debit? : Stripe element  }.
	 * @return {boolean} A flag for the event handler.
	 */
	const handlePaymentMethodCreation = (
		$form,
		successHandler,
		paymentMethodDetails
	) => {
		// We'll resubmit the form after populating our payment method, so if this is the second time this event
		// is firing we should let the form submission happen.
		if ( paymentMethodGenerated ) {
			paymentMethodGenerated = null;
			return;
		}

		blockUI( $form );
		const request = api.generatePaymentMethodRequest(
			paymentMethodDetails,
			preparedCustomerData
		);

		// Populate payment method owner details.
		const billingName = $( '#billing_first_name' ).length
			? (
					$( '#billing_first_name' ).val() +
					' ' +
					$( '#billing_last_name' ).val()
			  ).trim()
			: undefined;

		request.setBillingDetail( 'name', billingName );
		request.setBillingDetail( 'email', $( '#billing_email' ).val() );
		request.setBillingDetail( 'phone', $( '#billing_phone' ).val() );
		request.setAddressDetail( 'city', $( '#billing_city' ).val() );
		request.setAddressDetail( 'country', $( '#billing_country' ).val() );
		request.setAddressDetail( 'line1', $( '#billing_address_1' ).val() );
		request.setAddressDetail( 'line2', $( '#billing_address_2' ).val() );
		request.setAddressDetail(
			'postal_code',
			$( '#billing_postcode' ).val()
		);
		request.setAddressDetail( 'state', $( '#billing_state' ).val() );

		request
			.send()
			.then( ( { paymentMethod } ) => {
				// Flag that the payment method has been successfully generated so that we can allow the form
				// submission next time.
				paymentMethodGenerated = true;

				successHandler( $form, paymentMethod );
			} )
			.catch( ( error ) => {
				$form.removeClass( 'processing' ).unblock();
				showError( error.message );
			} );

		// Prevent form submission so that we can fire it once a payment method has been generated.
		return false;
	};

	/**
	 * Displays the authentication modal to the user if needed.
	 */
	const maybeShowAuthenticationModal = () => {
		const paymentMethodId = isWCPaySepaChosen()
			? $( '#wcpay-payment-method-sepa' ).val()
			: $( '#wcpay-payment-method' ).val();

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
		if ( isWCPayGiropayChosen() ) {
			// Giropay does not use saved payment methods at this time
			return false;
		}

		if ( isWCPaySepaChosen() ) {
			return (
				$( '#wc-woocommerce_payments_sepa-payment-token-new' ).length &&
				! $( '#wc-woocommerce_payments_sepa-payment-token-new' ).is(
					':checked'
				)
			);
		}
		return (
			$( '#wc-woocommerce_payments-payment-token-new' ).length &&
			! $( '#wc-woocommerce_payments-payment-token-new' ).is( ':checked' )
		);
	}

	let paymentElement = null;
	/**
	 * Sets up an intent based on a payment method.
	 *
	 * @param {string} paymentMethodId The ID of the payment method.
	 * @return {Promise} The final promise for the request to the server.
	 */
	const createPaymentIntent = () => {
		console.log( supportedUPEProperties );
		api.request( getConfig( 'ajaxUrl' ), {
			action: 'create_payment_intent_giropay',
			'wcpay-payment-method': 'giropay',
			// eslint-disable-next-line camelcase
			_ajax_nonce: getConfig( 'createSetupIntentNonce' ),
		} ).then( ( response ) => {
			console.log( response );

			if ( ! response.success ) {
				throw response.data.error;
			}

			if ( ! response.data.client_secret ) {
				throw new Error( 'Missing client secret.' );
			}
			console.log( response.data.client_secret );

			const appearance = {
				rules: {
					'.Input': getFieldStyles( upeThemeInputSelector ),
					'.Label': getFieldStyles( upeThemeLabelSelector ),
					'.Input--invalid, .Input--empty': getFieldStyles(
						'.woocommerce-checkout .form-row.woocommerce-invalid input'
					),
					'.Tab': getFieldStyles(
						'.woocommerce-checkout .form-row input'
					),
				},
			};
			console.log( appearance );

			paymentElement = elements.create( 'payment', {
				clientSecret: response.data.client_secret,
				appearance,
			} );
			paymentElement.mount( '.wc_payment_methods' );
			//return giropayRedirect( response.data.client_secret );
		} );
		return false;
	};

	// Handle the checkout form when WooCommerce Payments is chosen.
	const wcpayPaymentMethods = [
		PAYMENT_METHOD_NAME_CARD,
		PAYMENT_METHOD_NAME_GIROPAY,
		PAYMENT_METHOD_NAME_SEPA,
		PAYMENT_METHOD_NAME_SOFORT,
	];
	const checkoutEvents = wcpayPaymentMethods
		.map( ( method ) => `checkout_place_order_${ method }` )
		.join( ' ' );
	$( '.woocommerce-checkout' ).on( 'submit', function ( event ) {
		event.preventDefault();
		event.stopPropagation();
		console.log( 'CLICKED PLACE ORDER' );
		api.getStripe()
			.confirmPayment( {
				element: paymentElement,
				confirmParams: {
					return_url: 'http://bb-wcpay.jurassic.tube/123/complete',
				},
			} )
			.then( ( result ) => {
				console.log( result );
			} )
			.catch( ( error ) => {
				console.log( error.message );
			} );
		return false;
	} );

	// Handle the Pay for Order form if WooCommerce Payments is chosen.
	$( '#order_review' ).on( 'submit', () => {
		if (
			isUsingSavedPaymentMethod() ||
			( ! isWCPayCardChosen() && ! isWCPaySepaChosen() )
		) {
			return;
		}

		return handlePaymentMethodCreation(
			$( '#order_review' ),
			handleOrderPayment,
			isWCPaySepaChosen() ? sepaPayment : cardPayment
		);
	} );

	// Handle the add payment method form for WooCommerce Payments.
	$( 'form#add_payment_method' ).on( 'submit', function () {
		if ( ! $( '#wcpay-setup-intent' ).val() ) {
			let paymentMethodDetails = cardPayment;
			if ( isWCPaySepaChosen() ) {
				paymentMethodDetails = sepaPayment;
			} else if ( isWCPayGiropayChosen() ) {
				paymentMethodDetails = giropayPayment;
			} else if ( isWCPaySofortChosen() ) {
				paymentMethodDetails = sofortPayment;
			}
			return handlePaymentMethodCreation(
				$( 'form#add_payment_method' ),
				handleAddCard,
				paymentMethodDetails
			);
		}
	} );

	// On every page load, check to see whether we should display the authentication
	// modal and display it if it should be displayed.
	maybeShowAuthenticationModal();

	// Handle hash change - used when authenticating payment with SCA on checkout page.
	window.addEventListener( 'hashchange', () => {
		if ( window.location.hash.startsWith( '#wcpay-confirm-' ) ) {
			maybeShowAuthenticationModal();
		}
	} );

	window.getFieldStyles = ( selector ) => {
		if ( ! document.querySelector( selector ) ) {
			return {};
		}

		const elem = document.querySelector( selector );
		const styles = window.getComputedStyle( elem );
		const filteredStyles = {};

		for ( let i = 0; i < styles.length; i++ ) {
			const camelCase = dashedToCamelCase( styles[ i ] );
			if ( supportedUPEProperties.includes( camelCase ) ) {
				filteredStyles[ camelCase ] = styles.getPropertyValue(
					styles[ i ]
				);
			}
		}

		if ( upeColor ) {
			filteredStyles.color = upeColor;
		}

		if ( upeBorder ) {
			filteredStyles.border = upeBorder;
		}

		if ( upeFontFamily ) {
			filteredStyles.fontFamily = upeFontFamily;
		}

		return filteredStyles;
	};

	window.getFontURLs = () => {
		const pattern = /url\(.*?\)/g;
		for ( let i = 0; i < document.styleSheets.length; i++ ) {
			try {
				for (
					let j = 0;
					j < document.styleSheets[ i ].cssRules.length;
					j++
				) {
					const urls = document.styleSheets[ i ].cssRules[
						j
					].cssText.match( pattern );
					if ( urls ) {
						for ( let k = 0; k < urls.length; k++ ) {
							console.log( urls[ k ] );
						}
					}
				}
			} catch ( e ) {
				console.log( e );
				console.log( document.styleSheets[ i ] );
			}
		}
	};

	const dashedToCamelCase = ( string ) => {
		return string.replace( /-([a-z])/g, function ( g ) {
			return g[ 1 ].toUpperCase();
		} );
	};

	const camelCaseToDashed = ( string ) => {
		return string.replace( /[A-Z]/g, ( m ) => '-' + m.toLowerCase() );
	};

	createPaymentIntent();
} );
