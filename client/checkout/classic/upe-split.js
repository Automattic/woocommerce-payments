/* global jQuery */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import {
	PAYMENT_METHOD_NAME_BANCONTACT,
	PAYMENT_METHOD_NAME_BECS,
	PAYMENT_METHOD_NAME_CARD,
	PAYMENT_METHOD_NAME_EPS,
	PAYMENT_METHOD_NAME_GIROPAY,
	PAYMENT_METHOD_NAME_IDEAL,
	PAYMENT_METHOD_NAME_P24,
	PAYMENT_METHOD_NAME_SEPA,
	PAYMENT_METHOD_NAME_SOFORT,
	PAYMENT_METHOD_NAME_AFFIRM,
	PAYMENT_METHOD_NAME_AFTERPAY,
} from '../constants.js';
import { getUPEConfig } from 'utils/checkout';
import WCPayAPI from '../api';
import enqueueFraudScripts from 'fraud-scripts';
import { getFontRulesFromPage, getAppearance } from '../upe-styles';
import {
	getTerms,
	getPaymentIntentFromSession,
	getSelectedUPEGatewayPaymentMethod,
	getUpeSettings,
	isUsingSavedPaymentMethod,
	maybeTogglePaymentMethod,
} from '../utils/upe';
import { decryptClientSecret } from '../utils/encryption';
import enableStripeLinkPaymentMethod from '../stripe-link';
import apiRequest from '../utils/request';
import showErrorCheckout from '../utils/show-error-checkout';
import {
	getFingerprint,
	appendFingerprintInputToForm,
} from '../utils/fingerprint';
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';

jQuery( function ( $ ) {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );

	const publishableKey = getUPEConfig( 'publishableKey' );
	const isChangingPayment = getUPEConfig( 'isChangingPayment' );
	const isUPEEnabled = getUPEConfig( 'isUPEEnabled' );
	const isUPESplitEnabled = getUPEConfig( 'isUPESplitEnabled' );
	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const isStripeLinkEnabled =
		paymentMethodsConfig.link !== undefined &&
		paymentMethodsConfig.card !== undefined;

	const gatewayUPEComponents = {};
	for ( const paymentMethodType in paymentMethodsConfig ) {
		gatewayUPEComponents[ paymentMethodType ] = {
			elements: null,
			upeElement: null,
			paymentIntentId: null,
			paymentIntentClientSecret: null,
			isUPEComplete: null,
			country: null,
		};
	}

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	// Create an API object, which will be used throughout the checkout.
	const api = new WCPayAPI(
		{
			publishableKey,
			accountId: getUPEConfig( 'accountId' ),
			forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
			locale: getUPEConfig( 'locale' ),
			isUPEEnabled,
			isUPESplitEnabled,
			isStripeLinkEnabled,
		},
		apiRequest
	);
	let fingerprint = null;

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

	/**
	 * Converts form fields object into Stripe `shipping` object.
	 *
	 * @param {Object} fields Object mapping checkout shipping fields to values.
	 * @return {Object} Stripe formatted `shipping` object.
	 */
	const getShippingDetails = ( fields ) => {
		return {
			name:
				`${ fields.billing_first_name } ${ fields.billing_last_name }`.trim() ||
				'-',
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
			email:
				'string' === typeof fields.billing_email
					? fields.billing_email.trim()
					: '-',
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
	 * @param {string} paymentMethodType Stripe payment method type.
	 * @param {Object} upeDOMElement DOM element or HTML selector to use to mount UPE payment element.
	 * @param {boolean} isSetupIntent Set to true if we are on My Account adding a payment method.
	 */
	const mountUPEElement = async function (
		paymentMethodType,
		upeDOMElement,
		isSetupIntent = false
	) {
		// Do not mount UPE twice.
		const upeComponents = gatewayUPEComponents[ paymentMethodType ];
		let upeElement = upeComponents.upeElement;
		const paymentIntentId = upeComponents.paymentIntentId;
		if ( upeElement || paymentIntentId ) {
			return;
		}

		if ( ! fingerprint ) {
			try {
				const { visitorId } = await getFingerprint();
				fingerprint = visitorId;
			} catch ( error ) {
				// Do not mount element if fingerprinting is not available
				showErrorCheckout( error.message );

				return;
			}
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
		const isOrderPay = getUPEConfig( 'isOrderPay' );
		let orderId;
		if ( isOrderPay ) {
			orderId = getUPEConfig( 'orderId' );
		}

		let { intentId, clientSecret } = isSetupIntent
			? getSetupIntentFromSession( paymentMethodType )
			: getPaymentIntentFromSession(
					paymentMethodsConfig,
					paymentMethodType
			  );

		const $upeContainer = $( upeDOMElement );
		blockUI( $upeContainer );

		if ( ! intentId ) {
			try {
				const newIntent = isSetupIntent
					? await api.initSetupIntent( paymentMethodType )
					: await api.createIntent( {
							fingerprint,
							paymentMethodType,
							orderId,
					  } );
				intentId = newIntent.id;
				clientSecret = newIntent.client_secret;
			} catch ( error ) {
				unblockUI( $upeContainer );
				showErrorCheckout( error.message );
				const gatewayErrorMessage = __(
					'An error was encountered when preparing the payment form. Please try again later.',
					'woocommerce-payments'
				);
				$( '.payment_box.payment_method_woocommerce_payments' ).html(
					`<div>${ gatewayErrorMessage }</div>`
				);
			}
		}

		// I repeat, do NOT mount UPE twice.
		if ( upeElement || paymentIntentId ) {
			unblockUI( $upeContainer );
			return;
		}

		gatewayUPEComponents[ paymentMethodType ].paymentIntentId = intentId;

		let appearance = getUPEConfig( 'upeAppearance' );

		if ( ! appearance ) {
			appearance = getAppearance();
			api.saveUPEAppearance( appearance );
		}

		const elements = api.getStripe().elements( {
			clientSecret: decryptClientSecret( clientSecret ),
			appearance,
			fonts: getFontRulesFromPage(),
			loader: 'never',
		} );
		gatewayUPEComponents[ paymentMethodType ].elements = elements;

		if ( isStripeLinkEnabled ) {
			enableStripeLinkPaymentMethod( {
				api: api,
				elements: elements,
				emailId: 'billing_email',
				complete_billing: () => {
					return true;
				},
				complete_shipping: () => {
					return (
						document.getElementById(
							'ship-to-different-address-checkbox'
						) &&
						document.getElementById(
							'ship-to-different-address-checkbox'
						).checked
					);
				},
				shipping_fields: {
					line1: 'shipping_address_1',
					line2: 'shipping_address_2',
					city: 'shipping_city',
					state: 'shipping_state',
					postal_code: 'shipping_postcode',
					country: 'shipping_country',
					first_name: 'shipping_first_name',
					last_name: 'shipping_last_name',
				},
				billing_fields: {
					line1: 'billing_address_1',
					line2: 'billing_address_2',
					city: 'billing_city',
					state: 'billing_state',
					postal_code: 'billing_postcode',
					country: 'billing_country',
					first_name: 'billing_first_name',
					last_name: 'billing_last_name',
				},
			} );
		}

		upeElement = elements.create( 'payment', {
			...getUpeSettings(),
			wallets: {
				applePay: 'never',
				googlePay: 'never',
			},
		} );
		upeElement.mount( upeDOMElement );
		unblockUI( $upeContainer );
		upeElement.on( 'change', ( event ) => {
			const selectedUPEPaymentType =
				'link' !== event.value.type ? event.value.type : 'card';
			gatewayUPEComponents[ selectedUPEPaymentType ].country =
				event.value.country;
			gatewayUPEComponents[ selectedUPEPaymentType ].isUPEComplete =
				event.complete;
		} );
		gatewayUPEComponents[ paymentMethodType ].upeElement = upeElement;
	};

	// Only attempt to mount the card element once that section of the page has loaded. We can use the updated_checkout
	// event for this. This part of the page can also reload based on changes to checkout details, so we call unmount
	// first to ensure the card element is re-mounted correctly.
	$( document.body ).on( 'updated_checkout', () => {
		// If the card element selector doesn't exist, then do nothing (for example, when a 100% discount coupon is applied).
		// We also don't re-mount if already mounted in DOM.
		if (
			$( '.wcpay-upe-element' ).length &&
			! $( '.wcpay-upe-element' ).children().length &&
			isUPEEnabled
		) {
			const upeDOMElements = $( '.wcpay-upe-element' );
			for ( let i = 0; i < upeDOMElements.length; i++ ) {
				const upeDOMElement = upeDOMElements[ i ];
				const paymentMethodType = $( upeDOMElement ).attr(
					'data-payment-method-type'
				);

				const upeElement =
					gatewayUPEComponents[ paymentMethodType ].upeElement;
				if ( upeElement ) {
					upeElement.mount( upeDOMElement );
				} else {
					mountUPEElement( paymentMethodType, upeDOMElement );
				}

				if (
					paymentMethodsConfig[ paymentMethodType ].countries.length
				) {
					maybeTogglePaymentMethod(
						paymentMethodType,
						paymentMethodsConfig[ paymentMethodType ].countries
					);
					$( '#billing_country' ).on( 'change', function () {
						maybeTogglePaymentMethod(
							paymentMethodType,
							paymentMethodsConfig[ paymentMethodType ].countries
						);
					} );
				}
			}
		}
	} );

	if (
		$( 'form#add_payment_method' ).length ||
		$( 'form#order_review' ).length
	) {
		if (
			$( '.wcpay-upe-element' ).length &&
			! $( '.wcpay-upe-element' ).children().length &&
			isUPEEnabled
		) {
			// We use a setup intent if we are on the screens to add a new payment method or to change a subscription payment.
			const useSetUpIntent =
				$( 'form#add_payment_method' ).length || isChangingPayment;

			if ( isChangingPayment && getUPEConfig( 'newTokenFormId' ) ) {
				// Changing the method for a subscription takes two steps:
				// 1. Create the new payment method that will redirect back.
				// 2. Select the new payment method and resubmit the form to update the subscription.
				const token = getUPEConfig( 'newTokenFormId' );
				$( token ).prop( 'selected', true ).trigger( 'click' );
				$( 'form#order_review' ).submit();
			}
			const upeDOMElements = $( '.wcpay-upe-element' );
			for ( let i = 0; i < upeDOMElements.length; i++ ) {
				const upeDOMElement = upeDOMElements[ i ];
				const paymentMethodType = $( upeDOMElement ).attr(
					'data-payment-method-type'
				);
				mountUPEElement(
					paymentMethodType,
					upeDOMElement,
					useSetUpIntent
				);
			}
		}
	}

	/**
	 * Checks if UPE form is filled out. Displays errors if not.
	 *
	 * @param {Object} $form     The jQuery object for the form.
	 * @param {string} returnUrl The `return_url` param. Defaults to '#' (optional)
	 * @return {boolean} false if incomplete.
	 */
	const checkUPEForm = async ( $form, returnUrl = '#' ) => {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		const upeComponents = gatewayUPEComponents[ paymentMethodType ];
		const upeElement = upeComponents.upeElement;
		const elements = upeComponents.elements;
		const isUPEComplete = upeComponents.isUPEComplete;

		if ( ! upeElement ) {
			showErrorCheckout(
				__(
					'Your payment information is incomplete.',
					'woocommerce-payments'
				)
			);
			return false;
		}
		if ( ! isUPEComplete ) {
			// If UPE fields are not filled, confirm payment to trigger validation errors
			const { error } = await api.handlePaymentConfirmation(
				elements,
				{
					return_url: returnUrl,
				},
				null
			);
			$form.removeClass( 'processing' ).unblock();
			showErrorCheckout( error.message );
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
			getUPEConfig( 'orderReturnURL' ) +
			`&save_payment_method=${ savePaymentMethod }`;

		const orderId = getUPEConfig( 'orderId' );

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
			const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
			const upeComponents = gatewayUPEComponents[ paymentMethodType ];
			const updateResponse = await api.updateIntent(
				upeComponents.paymentIntentId,
				orderId,
				savePaymentMethod,
				paymentMethodType,
				upeComponents.country
			);

			if ( updateResponse.data ) {
				if ( updateResponse.data.error ) {
					throw updateResponse.data.error;
				}

				if ( api.handleDuplicatePayments( updateResponse.data ) ) {
					return;
				}
			}

			const { error } = await api.handlePaymentConfirmation(
				upeComponents.elements,
				{
					return_url: returnUrl,
				},
				getPaymentIntentSecret( paymentMethodType )
			);
			if ( error ) {
				throw error;
			}
		} catch ( error ) {
			$form.removeClass( 'processing' ).unblock();
			showErrorCheckout( error.message );
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
		const returnUrl = getUPEConfig( 'addPaymentReturnURL' );
		const isUPEFormValid = await checkUPEForm( $form, returnUrl );

		if ( ! isUPEFormValid ) {
			return;
		}

		blockUI( $form );

		try {
			const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
			const upeComponents = gatewayUPEComponents[ paymentMethodType ];
			const { error } = await api.getStripe().confirmSetup( {
				elements: upeComponents.elements,
				confirmParams: {
					return_url: returnUrl,
				},
			} );
			if ( error ) {
				throw error;
			}
		} catch ( error ) {
			$form.removeClass( 'processing' ).unblock();
			showErrorCheckout( error.message );
		}
	};

	/**
	 * Submits checkout form via AJAX to create order and uses custom
	 * redirect URL in AJAX response to request payment confirmation from UPE
	 *
	 * @param {Object} $form The jQuery object for the form.
	 * @param {string} paymentMethodType Stripe payment method type ID.
	 * @return {boolean} A flag for the event handler.
	 */
	const handleUPECheckout = async ( $form, paymentMethodType ) => {
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
			const upeComponents = gatewayUPEComponents[ paymentMethodType ];
			formFields.wcpay_payment_country = upeComponents.country;
			const response = await api.processCheckout(
				upeComponents.paymentIntentId,
				formFields,
				fingerprint ? fingerprint : ''
			);
			const redirectUrl = response.redirect_url;
			const upeConfig = {
				elements: upeComponents.elements,
				confirmParams: {
					return_url: redirectUrl,
					payment_method_data: {
						billing_details: getBillingDetails( formFields ),
					},
				},
			};
			// Afterpay requires shipping details to be passed. Not needed by other payment methods.
			if ( PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY === paymentMethodType ) {
				upeConfig.confirmParams.shipping = getShippingDetails(
					formFields
				);
			}
			let error;
			if ( response.payment_needed ) {
				( { error } = await api.handlePaymentConfirmation(
					upeComponents.elements,
					upeConfig.confirmParams,
					getPaymentIntentSecret( paymentMethodType )
				) );
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
			showErrorCheckout( error.message );
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
					errorMessage = getUPEConfig( 'genericErrorMessage' );
				}

				showErrorCheckout( errorMessage );
			} );
	};

	/**
	 * Returns stripe intent secret that will be used to confirm payment
	 *
	 * @param {string} paymentMethodType Stripe payment method type ID.
	 * @return {string | null} The intent secret required to confirm payment during the rate limit error.
	 */
	function getPaymentIntentSecret( paymentMethodType ) {
		const upeComponents = gatewayUPEComponents[ paymentMethodType ];
		if ( upeComponents.paymentIntentClientSecret ) {
			return upeComponents.paymentIntentClientSecret;
		}
		const { clientSecret } = getPaymentIntentFromSession(
			paymentMethodsConfig,
			paymentMethodType
		);
		return clientSecret ? clientSecret : null;
	}

	// Handle the checkout form when WooCommerce Payments is chosen.
	const wcpayPaymentMethods = [
		PAYMENT_METHOD_NAME_BANCONTACT,
		PAYMENT_METHOD_NAME_BECS,
		PAYMENT_METHOD_NAME_EPS,
		PAYMENT_METHOD_NAME_GIROPAY,
		PAYMENT_METHOD_NAME_IDEAL,
		PAYMENT_METHOD_NAME_P24,
		PAYMENT_METHOD_NAME_SEPA,
		PAYMENT_METHOD_NAME_SOFORT,
		PAYMENT_METHOD_NAME_AFFIRM,
		PAYMENT_METHOD_NAME_AFTERPAY,
		paymentMethodsConfig.card !== undefined && PAYMENT_METHOD_NAME_CARD,
	].filter( Boolean );
	const checkoutEvents = wcpayPaymentMethods
		.map( ( method ) => `checkout_place_order_${ method }` )
		.join( ' ' );
	$( 'form.checkout' ).on( checkoutEvents, function () {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			const paymentIntentId =
				gatewayUPEComponents[ paymentMethodType ].paymentIntentId;
			if ( isUPEEnabled && paymentIntentId ) {
				handleUPECheckout( $( this ), paymentMethodType );
				return false;
			}
		}

		appendFingerprintInputToForm( $( this ), fingerprint );
	} );

	// Handle the add payment method form for WooCommerce Payments.
	$( 'form#add_payment_method' ).on( 'submit', function () {
		// Skip adding legacy cards as UPE payment methods.
		if (
			'woocommerce_payments' ===
				$(
					"#add_payment_method input:checked[name='payment_method']"
				).val() &&
			'0' === isUPESplitEnabled
		) {
			return;
		}
		if ( ! $( '#wcpay-setup-intent' ).val() ) {
			const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
			const paymentIntentId =
				gatewayUPEComponents[ paymentMethodType ].paymentIntentId;
			if ( isUPEEnabled && paymentIntentId ) {
				handleUPEAddPayment( $( this ) );
				return false;
			}
		}
	} );

	// Handle the Pay for Order form if WooCommerce Payments is chosen.
	$( '#order_review' ).on( 'submit', () => {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if (
			! isUsingSavedPaymentMethod( paymentMethodType ) &&
			null !== paymentMethodType
		) {
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
			const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
			if ( ! paymentMethodType ) {
				return;
			}
			const upeElement =
				gatewayUPEComponents[ paymentMethodType ].upeElement;
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

/**
 * Returns the cached setup intent.
 *
 * @param {string} paymentMethodType Stripe payment method type ID.
 * @return {Object} The intent id and client secret required for mounting the UPE element.
 */
export function getSetupIntentFromSession( paymentMethodType ) {
	const paymentMethodsConfig = getUPEConfig( 'paymentMethodsConfig' );
	const upeSetupIntentData =
		paymentMethodsConfig[ paymentMethodType ].upeSetupIntentData;
	if ( upeSetupIntentData ) {
		const intentId = upeSetupIntentData.split( '-' )[ 0 ];
		const clientSecret = upeSetupIntentData.split( '-' )[ 1 ];
		return { intentId, clientSecret };
	}

	return {};
}
