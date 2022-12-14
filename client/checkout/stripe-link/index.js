/* global jQuery */

/**
 * Internal dependencies
 */
import { STRIPE_LINK_ACTIVE_CLASS } from '../utils/link.js';

const STRIPE_LINK_BUTTON_SELECTOR = '#wcpay-stripe-link-button-wrapper button';
const STRIPE_LINK_RADIO_SELECTOR = '#payment_method_woocommerce_payments_link';
let emailInputListener = null;

/**
 * Enables, disables, and manages Stripe Link checkout autofill.
 */
export default class StripeLinkButton {
	/**
	 * Constructor to set properties.
	 */
	constructor() {
		this.options = null;
		this.linkAutofill = null;
		this.isKeyupHandlerAttached = false;
		this.isAuthenticated = false;
		this.disableRequestButton();
	}

	/**
	 * Listener function for email input keyup event.
	 *
	 * @param {Object} event Keyup event.
	 */
	keyupHandler( event ) {
		this.linkAutofill.launch( { email: event.target.value } );
		this.disableRequestButton();
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
			this.enableRequestButton();
		}
	}

	/**
	 * Function to add email input listener.
	 */
	addEmailInputListener() {
		if ( ! this.isKeyupHandlerAttached ) {
			document
				.getElementById( this.options.emailId )
				.addEventListener( 'keyup', emailInputListener );
			this.isKeyupHandlerAttached = true;
		}
	}

	/**
	 * Function to remove email input listener.
	 */
	removeEmailInputListener() {
		if ( this.isKeyupHandlerAttached ) {
			document
				.getElementById( this.options.emailId )
				.removeEventListener( 'keyup', emailInputListener );
			this.isKeyupHandlerAttached = false;
		}
	}

	/**
	 * Make payment request button clickable.
	 */
	enableRequestButton() {
		jQuery( STRIPE_LINK_BUTTON_SELECTOR ).removeAttr( 'disabled' );
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

		this.disableRequestButton();
		const api = this.options.api;
		this.linkAutofill = api
			.getStripe()
			.linkAutofillModal( this.options.elements );
		emailInputListener = this.keyupHandler;

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
	}

	/**
	 * Activate Stripe Link at checkout. Stripe currently listening to email input.
	 *
	 * @return {undefined}
	 */
	enable() {
		jQuery( `#${ this.options.emailId }` ).addClass(
			STRIPE_LINK_ACTIVE_CLASS
		);

		this.addEmailInputListener();

		if ( this.isAuthenticated ) {
			this.linkAutofill.show();
			return;
		}

		const emailValue = jQuery( `#${ this.options.emailId }` ).val();
		// Trigger modal.
		if ( '' === emailValue ) {
			this.options.showError(
				'Please enter your email address to checkout with Link.'
			);
			jQuery( `#${ this.options.emailId }` ).focus();
		} else {
			this.linkAutofill.launch( { email: emailValue } );
			this.disableRequestButton();
		}
	}

	/**
	 * Deactivate Stripe Link at checkout. Stripe no longer listening to email input.
	 */
	disable() {
		jQuery( `#${ this.options.emailId }` ).removeClass(
			STRIPE_LINK_ACTIVE_CLASS
		);
		this.removeEmailInputListener();
		this.enableRequestButton();
	}
}
