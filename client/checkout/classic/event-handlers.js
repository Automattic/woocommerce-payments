/* global jQuery */

/**
 * Internal dependencies
 */
import './style.scss';
import { getUPEConfig } from 'wcpay/utils/checkout';
import {
	generateCheckoutEventNames,
	getSelectedUPEGatewayPaymentMethod,
	isLinkEnabled,
	hasPaymentMethodCountryRestrictions,
	isUsingSavedPaymentMethod,
	togglePaymentMethodForCountry,
	isBillingInformationMissing,
} from '../utils/upe';
import {
	processPayment,
	mountStripePaymentElement,
	mountStripePaymentMethodMessagingElement,
	renderTerms,
	createAndConfirmSetupIntent,
	maybeEnableStripeLink,
	blockUI,
	unblockUI,
} from './payment-processing';
import enqueueFraudScripts from 'fraud-scripts';
import { showAuthenticationModalIfRequired } from './3ds-flow-handling';
import WCPayAPI from 'wcpay/checkout/api';
import apiRequest from '../utils/request';
import { handleWooPayEmailInput } from 'wcpay/checkout/woopay/email-input-iframe';
import { isPreviewing } from 'wcpay/checkout/preview';
import { recordUserEvent } from 'tracks';
import '../utils/copy-test-number';
import { SHORTCODE_BILLING_ADDRESS_FIELDS } from '../constants';
import Visa from 'assets/images/payment-method-icons/visa.svg?asset';
import Mastercard from 'assets/images/payment-method-icons/mastercard.svg?asset';
import Amex from 'assets/images/payment-method-icons/amex.svg?asset';
import Discover from 'assets/images/payment-method-icons/discover.svg?asset';

jQuery( function ( $ ) {
	enqueueFraudScripts( getUPEConfig( 'fraudServices' ) );
	const publishableKey = getUPEConfig( 'publishableKey' );

	if ( ! publishableKey ) {
		// If no configuration is present, probably this is not the checkout page.
		return;
	}

	const $checkoutForm = $( 'form.checkout' );
	const $addPaymentMethodForm = $( 'form#add_payment_method' );
	const $payForOrderForm = $( 'form#order_review' );

	// creating a new jQuery object containing all the forms that need to be updated on submit, failure, or other events.
	const $forms = jQuery( $checkoutForm )
		.add( $addPaymentMethodForm )
		.add( $payForOrderForm );

	const api = new WCPayAPI(
		{
			publishableKey: publishableKey,
			accountId: getUPEConfig( 'accountId' ),
			forceNetworkSavedCards: getUPEConfig( 'forceNetworkSavedCards' ),
			locale: getUPEConfig( 'locale' ),
			isStripeLinkEnabled: isLinkEnabled(
				getUPEConfig( 'paymentMethodsConfig' )
			),
		},
		apiRequest
	);

	blockUI( $forms );
	showAuthenticationModalIfRequired( api ).finally( () => {
		unblockUI( $forms );
	} );

	$( document.body ).on( 'updated_checkout', () => {
		maybeMountStripePaymentElement( 'shortcode_checkout' );
		injectStripePMMEContainers();
		injectPaymentMethodLogos();
	} );

	$checkoutForm.on( generateCheckoutEventNames(), function () {
		if ( isBillingInformationMissing() ) {
			return;
		}

		return processPaymentIfNotUsingSavedMethod( $( this ) );
	} );

	$checkoutForm.on( 'click', '#place_order', function () {
		// Use the existing utility function to check if any WCPay payment method is selected
		const selectedPaymentMethod = getSelectedUPEGatewayPaymentMethod();

		if ( ! selectedPaymentMethod ) {
			return;
		}

		recordUserEvent( 'checkout_place_order_button_click' );
	} );

	window.addEventListener( 'hashchange', () => {
		if ( window.location.hash.startsWith( '#wcpay-confirm-' ) ) {
			blockUI( $forms );
			showAuthenticationModalIfRequired( api, $forms ).finally( () => {
				unblockUI( $forms );
			} );
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		if (
			event.target &&
			event.target.id === 'wc-woocommerce_payments-new-payment-method'
		) {
			renderTerms( event );
		}
	} );

	if ( $addPaymentMethodForm.length ) {
		maybeMountStripePaymentElement( 'add_payment_method' );
	}

	if ( $payForOrderForm.length ) {
		maybeMountStripePaymentElement( 'shortcode_checkout' );
	}

	$addPaymentMethodForm.on( 'submit', function () {
		if (
			$addPaymentMethodForm
				.find( "input:checked[name='payment_method']" )
				.val() !== 'woocommerce_payments'
		) {
			return;
		}

		// WC core calls block() when add_payment_method form is submitted, so we need to enable the ignore flag here to avoid
		// the overlay blink when the form is blocked twice.
		$.blockUI.defaults.ignoreIfBlocked = true;

		return processPayment(
			api,
			$addPaymentMethodForm,
			getSelectedUPEGatewayPaymentMethod(),
			createAndConfirmSetupIntent
		);
	} );

	$payForOrderForm.on( 'submit', function () {
		if ( getSelectedUPEGatewayPaymentMethod() === null ) {
			return;
		}

		return processPaymentIfNotUsingSavedMethod( $payForOrderForm );
	} );

	if (
		getUPEConfig( 'isWooPayEnabled' ) &&
		getUPEConfig( 'isWooPayEmailInputEnabled' ) &&
		! isPreviewing()
	) {
		handleWooPayEmailInput( '#billing_email', api );
	}

	async function injectStripePMMEContainers() {
		const paymentMethodsConfig =
			getUPEConfig( 'paymentMethodsConfig' ) || {};
		const bnplMethods = Object.keys( paymentMethodsConfig ).filter(
			( key ) => paymentMethodsConfig[ key ]?.isBnpl
		);

		const labelBase = 'payment_method_woocommerce_payments_';
		const paymentMethods = getUPEConfig( 'paymentMethodsConfig' );
		const paymentMethodsKeys = Object.keys( paymentMethods );
		const cartData = await api.pmmeGetCartData();

		for ( const method of paymentMethodsKeys ) {
			if ( bnplMethods.includes( method ) ) {
				const targetLabel = document.querySelector(
					`label[for="${ labelBase }${ method }"]`
				);
				const containerID = `stripe-pmme-container-${ method }`;

				if ( document.getElementById( containerID ) ) {
					document.getElementById( containerID ).innerHTML = '';
				}

				if ( targetLabel ) {
					// wrapInner target label in a span.woopayments-inner-label if it's not already
					let targetLabelInnerSpan = targetLabel.querySelector(
						'span.woopayments-inner-label'
					);
					if ( ! targetLabelInnerSpan ) {
						const targetLabelInner = targetLabel.innerHTML;
						targetLabel.innerHTML = '';
						targetLabelInnerSpan = document.createElement( 'span' );
						targetLabelInnerSpan.classList.add(
							'woopayments-inner-label'
						);
						targetLabelInnerSpan.innerHTML = targetLabelInner;
						targetLabel.appendChild( targetLabelInnerSpan );
					}

					let spacer = targetLabel.querySelector( 'span.spacer' );
					if ( ! spacer ) {
						spacer = document.createElement( 'span' );
						spacer.classList.add( 'spacer' );
						spacer.innerHTML = '&nbsp;';
						targetLabel.insertBefore(
							spacer,
							targetLabelInnerSpan
						);
					}

					let container = document.getElementById( containerID );
					if ( ! container ) {
						container = document.createElement( 'span' );
						container.id = containerID;
						container.dataset.paymentMethodType = method;
						container.classList.add( 'stripe-pmme-container' );
						targetLabelInnerSpan.appendChild( container );
					}

					const currentCountry =
						cartData?.billing_address?.country ||
						getUPEConfig( 'storeCountry' );

					if (
						paymentMethods[ method ]?.countries.length === 0 ||
						paymentMethods[ method ]?.countries?.includes(
							currentCountry
						)
					) {
						await mountStripePaymentMethodMessagingElement(
							api,
							container,
							{
								amount: cartData?.totals?.total_price,
								currency: cartData?.totals?.currency_code,
								decimalPlaces:
									cartData?.totals?.currency_minor_unit,
								country: currentCountry,
							},
							'shortcode_checkout'
						);
					}
				}
			}
		}
	}

	async function injectPaymentMethodLogos() {
		const cardLabel = document.querySelector(
			'label[for="payment_method_woocommerce_payments"]'
		);
		if ( ! cardLabel ) return;

		if ( cardLabel.querySelector( '.payment-methods--logos' ) ) return;

		const target = cardLabel.querySelector( 'img' );
		if ( ! target ) return;

		// Create container div
		const logosContainer = document.createElement( 'div' );
		logosContainer.className = 'payment-methods--logos';

		// Create inner div for flex layout
		const innerContainer = document.createElement( 'div' );
		innerContainer.setAttribute( 'role', 'button' );
		innerContainer.setAttribute( 'tabindex', '0' );
		innerContainer.setAttribute( 'data-testid', 'payment-methods-logos' );

		const paymentMethods = [
			{ name: 'visa', component: Visa },
			{ name: 'mastercard', component: Mastercard },
			{ name: 'amex', component: Amex },
			{ name: 'discover', component: Discover },
		];

		function getMaxElements() {
			const paymentMethodElement = document.querySelector(
				'.payment_method_woocommerce_payments'
			);
			if ( ! paymentMethodElement ) {
				return 4; // Default fallback
			}

			const elementWidth = paymentMethodElement.offsetWidth;
			if ( elementWidth <= 300 ) {
				return 1;
			} else if ( elementWidth <= 330 ) {
				return 2;
			}
		}

		function shouldHavePopover() {
			return paymentMethods.length > getMaxElements();
		}

		function createPopover( remainingMethods ) {
			const popover = document.createElement( 'div' );
			popover.className = 'logo-popover';
			popover.setAttribute( 'role', 'dialog' );
			popover.setAttribute(
				'aria-label',
				'Supported Credit Card Brands'
			);

			remainingMethods.forEach( ( pm ) => {
				const img = document.createElement( 'img' );
				img.src = pm.component;
				img.alt = pm.name;
				img.width = 38;
				img.height = 24;
				popover.appendChild( img );
			} );

			// Calculate number of items per row (max 5)
			const itemsPerRow = Math.min( remainingMethods.length, 5 );

			// Set grid-template-columns based on number of items
			popover.style.gridTemplateColumns = `repeat(${ itemsPerRow }, 38px)`;

			// Calculate width: (items * width) + (gaps * gap-size) + (padding * 2)
			const width = itemsPerRow * 38 + ( itemsPerRow - 1 ) * 8 + 16;
			popover.style.width = `${ width }px`;

			return popover;
		}

		function positionPopover( popover, anchor ) {
			const label = anchor.closest( 'label' );
			if ( ! label ) return;

			const labelRect = label.getBoundingClientRect();
			const labelStyle = window.getComputedStyle( label );
			const labelPaddingRight = parseInt( labelStyle.paddingRight, 10 );

			popover.style.position = 'fixed';
			popover.style.right = `${
				window.innerWidth - ( labelRect.right - labelPaddingRight )
			}px`;
			popover.style.top = `${ labelRect.top - 25 }px`;
			popover.style.zIndex = '1000';
			popover.style.left = 'auto';
		}

		function updateLogos() {
			innerContainer.innerHTML = ''; // Clear existing logos
			const maxElements = getMaxElements();
			const visibleMethods = paymentMethods.slice( 0, maxElements );
			const remainingCount = paymentMethods.length - maxElements;

			// Add visible logos
			visibleMethods.forEach( ( pm ) => {
				const brandImg = document.createElement( 'img' );
				brandImg.src = pm.component;
				brandImg.alt = pm.name;
				brandImg.width = 38;
				brandImg.height = 24;
				innerContainer.appendChild( brandImg );
			} );

			// Add count indicator if we should have a popover
			if ( shouldHavePopover() ) {
				const countDiv = document.createElement( 'div' );
				countDiv.className = 'payment-methods--logos-count';
				countDiv.textContent = `+ ${ remainingCount }`;

				// Add click handler directly to the count div
				countDiv.addEventListener( 'click', ( e ) => {
					e.stopPropagation();
					e.preventDefault();
					togglePopover();
				} );

				innerContainer.appendChild( countDiv );
			}

			// Remove existing popover if we no longer need it
			const existingPopover = cardLabel.querySelector( '.logo-popover' );
			if ( existingPopover && ! shouldHavePopover() ) {
				existingPopover.remove();
			}
		}

		function setupPopover() {
			const popover = createPopover(
				paymentMethods.slice( getMaxElements() )
			);
			cardLabel.appendChild( popover );
			positionPopover( popover, innerContainer );

			const handleResize = () =>
				positionPopover( popover, innerContainer );
			window.addEventListener( 'resize', handleResize );
			window.addEventListener( 'scroll', handleResize );

			const handlers = {};

			const cleanup = () => {
				popover.remove();
				window.removeEventListener( 'resize', handleResize );
				window.removeEventListener( 'scroll', handleResize );
				document.removeEventListener(
					'mousedown',
					handlers.handleOutsideClick
				);
				document.removeEventListener(
					'keydown',
					handlers.handleEscapeKey
				);
			};

			handlers.handleOutsideClick = ( e ) => {
				if (
					! popover.contains( e.target ) &&
					! innerContainer.contains( e.target )
				) {
					cleanup();
				}
			};

			handlers.handleEscapeKey = ( e ) => {
				if ( e.key === 'Escape' ) {
					cleanup();
				}
			};

			document.addEventListener(
				'mousedown',
				handlers.handleOutsideClick
			);
			document.addEventListener( 'keydown', handlers.handleEscapeKey );
		}

		function togglePopover() {
			if ( ! shouldHavePopover() ) return;

			const existingPopover = cardLabel.querySelector( '.logo-popover' );
			if ( existingPopover ) {
				existingPopover.remove();
				return;
			}

			setupPopover();
		}

		// Remove the click handler from innerContainer since we're handling it on the count div
		// Keep the keyboard handler for accessibility
		innerContainer.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				e.stopPropagation();
				togglePopover();
			}
		} );

		// Initial setup
		logosContainer.appendChild( innerContainer );
		target.replaceWith( logosContainer );
		updateLogos();

		// Update on window resize
		window.addEventListener( 'resize', updateLogos );
	}

	function processPaymentIfNotUsingSavedMethod( $form ) {
		const paymentMethodType = getSelectedUPEGatewayPaymentMethod();
		if ( ! isUsingSavedPaymentMethod( paymentMethodType ) ) {
			return processPayment( api, $form, paymentMethodType );
		}
	}

	async function maybeMountStripePaymentElement( elementsLocation ) {
		const $upeForms = $( '.wcpay-upe-form' );
		const $upeElements = $upeForms.find( '.wcpay-upe-element' );

		if ( $upeElements.length && ! $upeElements.children().length ) {
			for ( const upeElement of $upeElements.toArray() ) {
				await mountStripePaymentElement(
					api,
					upeElement,
					elementsLocation
				);
				restrictPaymentMethodToLocation( upeElement );
			}
			maybeEnableStripeLink( api );
		}
	}

	function restrictPaymentMethodToLocation( upeElement ) {
		if ( hasPaymentMethodCountryRestrictions( upeElement ) ) {
			togglePaymentMethodForCountry( upeElement );

			const billingInput = upeElement
				?.closest( 'form.checkout' )
				?.querySelector(
					`[name="${ SHORTCODE_BILLING_ADDRESS_FIELDS.country }"]`
				);
			if ( billingInput ) {
				// this event only applies to the checkout form, but not "place order" or "add payment method" pages.
				$( billingInput ).on( 'change', function () {
					togglePaymentMethodForCountry( upeElement );
				} );
			}
		}
	}
} );
