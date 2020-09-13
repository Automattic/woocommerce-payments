/* eslint-disable strict, no-var */
/* global jQuery, Stripe, wcpay_config */

/**
 * Internal dependencies
 */
import './style.scss';

jQuery( function ( $ ) {
	'use strict';

	/* eslint-disable-next-line camelcase */
	var stripe = new Stripe( wcpay_config.publishableKey, {
		/* eslint-disable-next-line camelcase */
		stripeAccount: wcpay_config.accountId,
	} );
	var elements = stripe.elements();

	// In the future this object will be loaded with customer information through `wp_localize_script`.
	var preparedCustomerData = {};

	// Create a card element.
	var cardElement = elements.create( 'card', {
		hidePostalCode: true,
		classes: { base: 'wcpay-card-mounted' },
	} );

	// Only attempt to mount the card element once that section of the page has loaded. We can use the updated_checkout
	// event for this. This part of the page can also reload based on changes to checkout details, so we call unmount
	// first to ensure the card element is re-mounted correctly.
	$( document.body ).on( 'updated_checkout', function () {
		// Don't re-mount if already mounted in DOM.
		if ( $( '#wcpay-card-element' ).children().length ) {
			return;
		}

		cardElement.unmount();
		cardElement.mount( '#wcpay-card-element' );
	} );

	if (
		$( 'form#add_payment_method' ).length ||
		$( 'form#order_review' ).length
	) {
		cardElement.mount( '#wcpay-card-element' );
	}

	// Update the validation state based on the element's state.
	cardElement.addEventListener( 'change', function ( event ) {
		var displayError = jQuery( '#wcpay-errors' );
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
	 * @param {object} $form The jQuery object for the form.
	 */
	var blockUI = function ( $form ) {
		$form.addClass( 'processing' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6,
			},
		} );
	};

	/**
	 * Adds a customer value to an object if the value exists and is non-empty.
	 *
	 * @param {object} customerObj The object that the value should be loaded to.
	 * @param {string} prop        The name of the prop in the object.
	 * @param {string} inputId     The ID of the input on the page (or the data, preloaded by the server.)
	 */
	var setCustomerValue = function ( customerObj, prop, inputId ) {
		var value;

		// Try to load the value from the fields on the page.
		if ( 'name' === inputId ) {
			// If and whenever the first/last name fields do not exist on the page, this will be an empty string.
			value = (
				$( '#billing_first_name' ).val() +
				' ' +
				$( '#billing_last_name' ).val()
			).trim();
		} else {
			// No need to check whether the element exists, `$.fn.val()` would return `undefined`.
			value = $( '#' + inputId ).val();
		}

		// Fall back to the value in `preparedCustomerData`.
		if ( 'undefined' === typeof value || 0 === value.length ) {
			value = preparedCustomerData[ inputId ]; // `undefined` if not set.
		}

		if ( 'undefined' !== typeof value && 0 < value.length ) {
			customerObj[ prop ] = value;
		}
	};

	/**
	 * Loads all necessary billing details for payment methods.
	 *
	 * @return {object} An object, containing email, name, phone & an address.
	 */
	var loadBillingDetails = function () {
		var billingDetails = {},
			billingAddress = {};

		// Populate billing details.
		setCustomerValue( billingDetails, 'name', 'name' );
		setCustomerValue( billingDetails, 'email', 'billing_email' );
		setCustomerValue( billingDetails, 'phone', 'billing_phone' );

		// Populate the billing address.
		setCustomerValue( billingAddress, 'city', 'billing_city' );
		setCustomerValue( billingAddress, 'country', 'billing_country' );
		setCustomerValue( billingAddress, 'line1', 'billing_address_1' );
		setCustomerValue( billingAddress, 'line2', 'billing_address_2' );
		setCustomerValue( billingAddress, 'postal_code', 'billing_postcode' );
		setCustomerValue( billingAddress, 'state', 'billing_state' );

		billingDetails.address = billingAddress;
		return billingDetails;
	};

	// Show error notice at top of checkout form.
	var showError = function ( errorMessage ) {
		var messageWrapper =
			'<ul class="woocommerce-error" role="alert">' +
			errorMessage +
			'</ul>';
		var $container = $(
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

		var scrollElement = $( '.woocommerce-NoticeGroup-checkout' );
		if ( ! scrollElement.length ) {
			scrollElement = $container;
		}

		$.scroll_to_notices( scrollElement );
		$( document.body ).trigger( 'checkout_error' );
	};

	// Create payment method on submission.
	var paymentMethodGenerated;

	/**
	 * Saves the payment method ID in a hidden input, and re-submits the form.
	 *
	 * @param {object} $form         The jQuery object for the form.
	 * @param {object} paymentMethod Payment method object.
	 */
	var handleOrderPayment = function ( $form, paymentMethod ) {
		var id = paymentMethod.id;

		// Flag that the payment method has been successfully generated so that we can allow the form
		// submission next time.
		paymentMethodGenerated = true;

		// Populate form with the payment method.
		var paymentMethodInput = document.getElementById(
			'wcpay-payment-method'
		);
		paymentMethodInput.value = id;

		// Re-submit the form.
		$form.removeClass( 'processing' ).submit();
	};

	/**
	 * Creates and authorizes a setup intent, saves its ID in a hidden input, and re-submits the form.
	 *
	 * @param {object} $form         The jQuery object for the form.
	 * @param {object} paymentMethod Payment method object.
	 */
	var handleAddCard = function ( $form, paymentMethod ) {
		/* eslint-disable camelcase */
		$.post( wcpay_config.ajaxUrl, {
			action: 'create_setup_intent',
			'wcpay-payment-method': paymentMethod.id,
			_ajax_nonce: wcpay_config.createSetupIntentNonce,
		} )
			/* eslint-enable camelcase */
			.then( function ( response ) {
				if ( ! response.success ) {
					return $.Deferred().reject( response.data.error );
				}

				var setupIntent = response.data;

				stripe
					.confirmCardSetup( setupIntent.client_secret, {
						// eslint-disable-next-line camelcase
						payment_method: paymentMethod.id,
					} )
					.then( function ( result ) {
						var confirmedSetupIntent = result.setupIntent;
						var error = result.error;

						if ( error ) {
							throw error;
						}

						return confirmedSetupIntent;
					} )
					.then( function ( confirmedSetupIntent ) {
						// Populate form with the setup intent and re-submit.
						var setupIntentInput = $(
							'<input type="hidden" id="wcpay-setup-intent" name="wcpay-setup-intent" />'
						);
						setupIntentInput.val( confirmedSetupIntent.id );
						$form.append( setupIntentInput );

						// WC core calls block() when add_payment_form is submitted, so we need to enable the ignore flag here to avoid
						// the overlay blink when the form is blocked twice. We can restore its default value once the form is submitted.
						var defaultIgnoreIfBlocked =
							$.blockUI.defaults.ignoreIfBlocked;
						$.blockUI.defaults.ignoreIfBlocked = true;

						// Re-submit the form.
						$form.removeClass( 'processing' ).submit();

						// Restore default value for ignoreIfBlocked.
						$.blockUI.defaults.ignoreIfBlocked = defaultIgnoreIfBlocked;
					} )
					.catch( function ( error ) {
						$form.removeClass( 'processing' ).unblock();
						showError( error.message );
					} );
			} )
			.fail( function ( error ) {
				$form.removeClass( 'processing' ).unblock();
				showError( error.message );
			} );
	};

	/**
	 * Generates a payment method and executes the successHandler callback.
	 *
	 * @param {object}   $form             The jQuery object for the form.
	 * @param {function} successHandler    Callback to be executed when payment method is generated.
	 * @param {boolean}  useBillingDetails Flag to control whether to use from billing details or not.
	 * @return {boolean} A flag for the event handler.
	 */
	var handlePaymentMethodCreation = function (
		$form,
		successHandler,
		useBillingDetails = true
	) {
		// We'll resubmit the form after populating our payment method, so if this is the second time this event
		// is firing we should let the form submission happen.
		if ( paymentMethodGenerated ) {
			paymentMethodGenerated = null;
			return;
		}

		blockUI( $form );

		var paymentMethodArgs = {
			type: 'card',
			card: cardElement,
		};

		if ( useBillingDetails ) {
			// eslint-disable-next-line camelcase
			paymentMethodArgs.billing_details = loadBillingDetails();
		}

		stripe
			.createPaymentMethod( paymentMethodArgs )
			.then( function ( result ) {
				var paymentMethod = result.paymentMethod;
				var error = result.error;

				if ( error ) {
					throw error;
				}

				return paymentMethod;
			} )
			.then( function ( paymentMethod ) {
				successHandler( $form, paymentMethod );
			} )
			.catch( function ( error ) {
				$form.removeClass( 'processing' ).unblock();
				showError( error.message );
			} );

		// Prevent form submission so that we can fire it once a payment method has been generated.
		return false;
	};

	/**
	 * Shows the authentication modal to the user and handles the outcome.
	 *
	 * @param {string} orderId      The ID of the order being paid for.
	 * @param {string} clientSecret The client secret of the intent being used to pay for the order.
	 */
	var showAuthenticationModal = function ( orderId, clientSecret ) {
		stripe
			.confirmCardPayment( clientSecret )
			.then( function ( result ) {
				var paymentMethodId = document.getElementById(
					'wcpay-payment-method'
				).value;
				var savePaymentMethod = document.getElementById(
					'wc-woocommerce_payments-new-payment-method'
				).checked;
				var intentId =
					( result.paymentIntent && result.paymentIntent.id ) ||
					( result.error &&
						result.error.payment_intent &&
						result.error.payment_intent.id );
				return [
					// eslint-disable-next-line camelcase
					jQuery.post( wcpay_config.ajaxUrl, {
						action: 'update_order_status',
						// eslint-disable-next-line camelcase
						order_id: orderId,
						// eslint-disable-next-line camelcase
						_ajax_nonce: wcpay_config.updateOrderStatusNonce,
						// eslint-disable-next-line camelcase
						intent_id: intentId,
						// eslint-disable-next-line camelcase
						payment_method_id: savePaymentMethod
							? paymentMethodId
							: null,
					} ),
					result.error,
				];
			} )
			.then( function ( [ response, originalError ] ) {
				// If there was a prblem with the paymeent, we can show the
				// error message to the user immediately, and we don't need
				// to wait for the `update_order_status` request to complete.
				if ( originalError ) {
					throw originalError;
				}

				// Otherwise, we are waiting for the `update_order_status` request to complete.
				return response;
			} )
			.then( function ( response ) {
				var result = JSON.parse( response );

				if ( result.error ) {
					throw result.error;
				}

				window.location = result.return_url;
			} )
			.catch( function ( error ) {
				$( 'form.checkout' ).removeClass( 'processing' ).unblock();
				$( '#order_review' ).removeClass( 'processing' ).unblock();
				$( '#payment' ).show( 500 );

				var errorMessage = error.message;

				// If this is a generic error, we probably don't want to display the error message to the user,
				// so display a generic message instead.
				if ( error instanceof Error ) {
					// eslint-disable-next-line camelcase
					errorMessage = wcpay_config.genericErrorMessage;
				}

				showError( errorMessage );
			} );
	};

	/**
	 * Displays the authentication modal to the user if needed.
	 */
	function maybeShowAuthenticationModal() {
		var partials = window.location.hash.match(
			/^#wcpay-confirm-pi:(.+):(.+):(.+)$/
		);

		if ( ! partials ) {
			return;
		}

		var orderPayIndex = document.location.href.indexOf( 'order-pay' );
		var isOrderPage = orderPayIndex > -1;

		if ( isOrderPage ) {
			blockUI( $( '#order_review' ) );
			$( '#payment' ).hide( 500 );
		}

		var orderId = partials[ 1 ];
		var clientSecret = partials[ 2 ];
		// Update the current order status nonce with the new one to ensure that the update
		// order status call works when a guest user creates an account during checkout.
		// eslint-disable-next-line camelcase
		wcpay_config.updateOrderStatusNonce = partials[ 3 ];

		// If we're on the Pay for Order page, get the order ID
		// directly from the URL instead of relying on the hash.
		// The checkout URL does not contain the string 'order-pay'.
		// The Pay for Order page contains the string 'order-pay' and
		// can have these formats:
		// Plain permalinks:
		// /?page_id=7&order-pay=189&pay_for_order=true&key=wc_order_key
		// Non-plain permalinks:
		// /checkout/order-pay/189/
		// Match for consecutive digits after the string 'order-pay' to get the order ID.
		var orderIdPartials =
			isOrderPage &&
			window.location.href.substring( orderPayIndex ).match( /\d+/ );
		if ( orderIdPartials ) {
			orderId = orderIdPartials[ 0 ];
		}

		// Cleanup the URL.
		// https://stackoverflow.com/questions/1397329/
		// how-to-remove-the-hash-from-window-location-url-with-javascript-without-page-r/
		// 5298684#5298684
		history.replaceState(
			'',
			document.title,
			window.location.pathname + window.location.search
		);

		showAuthenticationModal( orderId, clientSecret );
	}

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
	$( 'form.checkout' ).on(
		'checkout_place_order_woocommerce_payments',
		function () {
			if ( ! isUsingSavedPaymentMethod() ) {
				return handlePaymentMethodCreation(
					$( this ),
					handleOrderPayment
				);
			}
		}
	);

	// Handle the Pay for Order form if WooCommerce Payments is chosen.
	$( '#order_review' ).on( 'submit', function () {
		if (
			$( '#payment_method_woocommerce_payments' ).is( ':checked' ) &&
			! isUsingSavedPaymentMethod()
		) {
			return handlePaymentMethodCreation(
				$( '#order_review' ),
				handleOrderPayment
			);
		}
	} );

	// Handle the add payment method form for WooCommerce Payments.
	$( 'form#add_payment_method' ).on( 'submit', function () {
		if ( ! $( '#wcpay-setup-intent' ).val() ) {
			return handlePaymentMethodCreation(
				$( 'form#add_payment_method' ),
				handleAddCard,
				false
			);
		}
	} );

	// On every page load, check to see whether we should display the authentication
	// modal and display it if it should be displayed.
	maybeShowAuthenticationModal();

	// Handle hash change - used when authenticating payment with SCA on checkout page.
	window.addEventListener( 'hashchange', function () {
		if ( 0 < window.location.hash.startsWith( '#wcpay-confirm-pi' ) ) {
			maybeShowAuthenticationModal();
		}
	} );
} );
