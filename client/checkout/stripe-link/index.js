/* global jQuery */

/**
 * Internal dependencies
 */
import { validateEmail, Spinner } from 'wcpay/utils/checkout';
import {
	STRIPE_LINK_AUTHENTICATED_CLASS,
	getWooPayQueryStatus,
	isLinkModalOpen,
	setLinkModalStatus,
	getLinkModalStatus,
} from '../utils/link.js';

const STRIPE_LINK_BUTTON_SELECTOR = '#wcpay-stripe-link-button-wrapper button';
const STRIPE_LINK_RADIO_SELECTOR = '#payment_method_woocommerce_payments_link';

/**
 * Enables, disables, and manages Stripe Link checkout autofill.
 */
export default class StripeLinkButton {
	/**
	 * Constructor to set properties.
	 *
	 * @param {boolean} isPlatformCheckoutEnabled Is WooPay available at checkout.
	 */
	constructor( isPlatformCheckoutEnabled ) {
		this.options = null;
		this.linkAutofill = null;
		this.isAuthenticated = false;
		this.isKeyupHandlerAttached = false;
		this.isUPELoaded = false;
		this.isPlatformCheckoutEnabled = isPlatformCheckoutEnabled;
		this.removeEmailInputListener = null;
		this.spinner = null;
		this.isModalClosed = false;
		this.disableRequestButton();
	}

	/**
	 * Interval to check when OTP modal is opened and then closed, when Link can then be disabled.
	 */
	startModalWatcher() {
		const emailInput = document.getElementById( this.options.emailId );
		const modalWatcherInterval = setInterval( () => {
			const modalStatus = getLinkModalStatus( emailInput );
			if ( isLinkModalOpen() && ! modalStatus ) {
				// Modal is open, status unset.
				setLinkModalStatus( emailInput, true );
			} else if ( 'open' === modalStatus ) {
				// Modal is closed, previously open.
				if ( ! this.isAuthenticated ) {
					setLinkModalStatus( emailInput, false );
					this.isModalClosed = true;
					this.disableRequestButton();
					this.removeEmailInputListener();
				}
				clearInterval( modalWatcherInterval );
			}
		}, 100 );
	}

	/**
	 * Displays preloading spinner for 10s, before hiding.
	 */
	flashSpinner() {
		if ( ! this.spinner.getSpinner() ) {
			this.spinner.show();
		}
		setTimeout( () => {
			this.spinner.remove();
		}, 10 * 1000 );
	}

	/**
	 * Try Autofill authentication by querying Stripe for email address.
	 *
	 * @param {string} email Email address.
	 */
	tryAuthentication( email ) {
		this.linkAutofill.launch( { email } );
		this.flashSpinner();
		this.disableRequestButton();
	}

	/**
	 * Queries Stripe Link for user's email address and launches autofill, if account found.
	 *
	 * @param {string} email User email address value.
	 */
	launchAutofill( email ) {
		if ( ! validateEmail( email ) ) {
			return;
		}

		if ( this.isPlatformCheckoutEnabled ) {
			const emailInput = document.getElementById( this.options.emailId );
			switch ( getWooPayQueryStatus( emailInput ) ) {
				// Email address belongs to registered WooPay user.
				case 'true':
					this.enableRequestButton();
					// Do not open modal.
					break;
				// Email address does not belong to registered WooPay user;
				case 'false':
					this.tryAuthentication( email );
					break;
				// Still querying for WooPay registration.
				case 'checking':
					// Retry in a second.
					setTimeout( () => {
						this.launchAutofill( email );
					}, 1000 );
					break;
				default:
					this.tryAuthentication( email );
			}
		} else {
			this.tryAuthentication( email );
		}
	}

	/**
	 * Listener function for email input keyup event.
	 *
	 * @param {Object} event Keyup event.
	 */
	keyupHandler( event ) {
		this.launchAutofill( event.target.value );
	}

	/**
	 * Listener function for click on payment request button.
	 *
	 * @param {Object} event Click event.
	 * @return {undefined}
	 */
	clickHandler( event ) {
		event.preventDefault();
		jQuery( STRIPE_LINK_RADIO_SELECTOR ).click();
		this.enable();
	}

	/**
	 * Function to autofill checkout fields after successful authentication.
	 *
	 * @param {Object} event Stripe autofill event value.
	 */
	autofillHandler( event ) {
		const { billingAddress, shippingAddress } = event.value;
		const fillWith = this.options.fill_field_method
			? this.options.fill_field_method
			: ( address, nodeId, key ) => {
					if ( null !== document.getElementById( nodeId ) ) {
						document.getElementById( nodeId ).value =
							address.address[ key ];
					}
			  };

		if ( this.options.complete_shipping() ) {
			const shippingNames = shippingAddress.name.split( / (.*)/s, 2 );
			shippingAddress.address.last_name = shippingNames[ 1 ];
			shippingAddress.address.first_name = shippingNames[ 0 ];

			fillWith(
				shippingAddress,
				this.options.shipping_fields.line1,
				'line1'
			);
			fillWith(
				shippingAddress,
				this.options.shipping_fields.line2,
				'line2'
			);
			fillWith(
				shippingAddress,
				this.options.shipping_fields.city,
				'city'
			);
			fillWith(
				shippingAddress,
				this.options.shipping_fields.country,
				'country'
			);
			fillWith(
				shippingAddress,
				this.options.shipping_fields.first_name,
				'first_name'
			);
			fillWith(
				shippingAddress,
				this.options.shipping_fields.last_name,
				'last_name'
			);
			jQuery(
				'#billing_country, #billing_state, #shipping_country, #shipping_state'
			).trigger( 'change' );
			fillWith(
				shippingAddress,
				this.options.shipping_fields.state,
				'state'
			);
			fillWith(
				shippingAddress,
				this.options.shipping_fields.postal_code,
				'postal_code'
			);
		}

		if ( this.options.complete_billing() ) {
			const billingNames = billingAddress.name.split( / (.*)/s, 2 );
			billingAddress.address.last_name = billingNames[ 1 ];
			billingAddress.address.first_name = billingNames[ 0 ];

			fillWith(
				billingAddress,
				this.options.billing_fields.line1,
				'line1'
			);
			fillWith(
				billingAddress,
				this.options.billing_fields.line2,
				'line2'
			);
			fillWith(
				billingAddress,
				this.options.billing_fields.city,
				'city'
			);
			fillWith(
				billingAddress,
				this.options.billing_fields.country,
				'country'
			);
			fillWith(
				billingAddress,
				this.options.billing_fields.first_name,
				'first_name'
			);
			fillWith(
				billingAddress,
				this.options.billing_fields.last_name,
				'last_name'
			);

			jQuery(
				'#billing_country, #billing_state, #shipping_country, #shipping_state'
			).trigger( 'change' );
			fillWith(
				billingAddress,
				this.options.billing_fields.state,
				'state'
			);
			fillWith(
				billingAddress,
				this.options.billing_fields.postal_code,
				'postal_code'
			);
		}
		jQuery(
			'#billing_country, #billing_state, #shipping_country, #shipping_state'
		).trigger( 'change' );
	}

	/**
	 * Authentication event handler.
	 *
	 * @param {Object} event Stripe authentication event.
	 */
	authenticationHandler( event ) {
		if ( ! event.empty ) {
			this.isAuthenticated = true;
			jQuery( `#${ this.options.emailId }` ).addClass(
				STRIPE_LINK_AUTHENTICATED_CLASS
			);
			this.spinner.remove();
			this.enableRequestButton();
		}
	}

	/**
	 * Function to add email input listener.
	 */
	addEmailInputListener() {
		if ( ! this.isKeyupHandlerAttached ) {
			const emailInputListener = ( event ) => {
				setTimeout( () => {
					this.keyupHandler( event );
				}, 500 );
			};
			document
				.getElementById( this.options.emailId )
				.addEventListener( 'keyup', emailInputListener );
			this.isKeyupHandlerAttached = true;

			this.removeEmailInputListener = () => {
				if ( this.isKeyupHandlerAttached ) {
					document
						.getElementById( this.options.emailId )
						.removeEventListener( 'keyup', emailInputListener );
					this.isKeyupHandlerAttached = false;
				}
			};
		}
	}

	/**
	 * Make payment request button clickable.
	 */
	enableRequestButton() {
		if ( ! this.isModalClosed ) {
			jQuery( STRIPE_LINK_BUTTON_SELECTOR ).prop( 'disabled', false );
		}
	}

	/**
	 * Disable payment request button.
	 */
	disableRequestButton() {
		jQuery( STRIPE_LINK_BUTTON_SELECTOR ).prop( 'disabled', true );
	}

	/**
	 * Initialiser for button.
	 *
	 * @param {Object} options Stripe Link button options.
	 * @return {undefined}
	 */
	init( options ) {
		if ( ! document.getElementById( options.emailId ) ) {
			return;
		}
		this.options = options;

		const api = this.options.api;
		this.linkAutofill = api
			.getStripe()
			.linkAutofillModal( this.options.elements );

		// Handle StripeLink button click.
		jQuery( STRIPE_LINK_BUTTON_SELECTOR ).on( 'click', ( event ) => {
			this.clickHandler( event );
		} );

		// Handle autofill fields post authentication.
		this.linkAutofill.on( 'autofill', ( event ) => {
			this.autofillHandler( event );
		} );

		// Handle successful authentication.
		this.linkAutofill.on( 'authenticated', ( event ) => {
			this.authenticationHandler( event );
		} );

		this.spinner = new Spinner(
			document.getElementById( options.emailId )
		);
		this.enableRequestButton();
	}

	/**
	 * Activate Stripe Link at checkout. Stripe currently listening to email input.
	 *
	 * @return {undefined}
	 */
	enable() {
		if ( ! this.linkAutofill ) {
			return;
		}

		this.startModalWatcher();
		this.addEmailInputListener();

		if ( this.isAuthenticated ) {
			this.linkAutofill.show();
			return;
		}

		const emailValue = jQuery( `#${ this.options.emailId }` ).val();
		this.launchAutofill( emailValue );
		this.isUPELoaded = true;
	}

	/**
	 * Deactivate Stripe Link at checkout. Stripe no longer listening to email input.
	 */
	disable() {
		this.removeEmailInputListener();
		this.enableRequestButton();
	}
}
