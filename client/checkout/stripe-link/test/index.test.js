/**
 * Internal dependencies
 */
import enableStripeLinkPaymentMethod from '..';
import WCPayAPI from 'wcpay/checkout/api';

jest.mock( 'wcpay/checkout/api', () => {
	const mockOn = jest.fn();
	const mockLaunch = jest.fn();

	const mockLaunchAutofillModal = jest.fn( () => {
		return {
			launch: mockLaunch,
			on: mockOn,
		};
	} );

	const mockedStripe = jest.fn( () => {
		return {
			linkAutofillModal: mockLaunchAutofillModal,
		};
	} );

	return jest.fn().mockImplementation( () => {
		return {
			getStripe: mockedStripe,
		};
	} );
} );

const billingEmail = 'example@example.com';

describe( 'Stripe Link elements behavior', () => {
	test( 'Should stop if emailId is not found', () => {
		enableStripeLinkPaymentMethod( {
			emailId: 'not_existing_email@example.com',
		} );
		expect(
			WCPayAPI().getStripe().linkAutofillModal
		).not.toHaveBeenCalled();
	} );

	test( 'Should call linkAutofillModal when email is present', () => {
		createStripeLinkElements();
		enableStripeLinkPaymentMethod( {
			api: WCPayAPI(),
			emailId: 'billing_email',
		} );
		expect( WCPayAPI().getStripe().linkAutofillModal ).toHaveBeenCalled();
	} );

	test( 'Should add keyup event listener to email input', () => {
		createStripeLinkElements();
		const billingEmailInput = document.getElementById( 'billing_email' );
		const addEventListenerSpy = jest.spyOn(
			billingEmailInput,
			'addEventListener'
		);

		enableStripeLinkPaymentMethod( {
			api: WCPayAPI(),
			emailId: 'billing_email',
		} );

		billingEmailInput.dispatchEvent( new Event( 'keyup' ) );
		expect( addEventListenerSpy ).toHaveBeenCalledWith(
			'keyup',
			expect.any( Function )
		);
		expect(
			WCPayAPI().getStripe().linkAutofillModal().launch
		).toHaveBeenCalledWith( { email: billingEmail } );
	} );

	test( 'Stripe Link button should be visible and clickable', () => {
		createStripeLinkElements();
		const stripeLinkButton = document.getElementsByClassName(
			'wcpay-stripelink-modal-trigger'
		)[ 0 ];
		const addEventListenerSpy = jest.spyOn(
			stripeLinkButton,
			'addEventListener'
		);

		enableStripeLinkPaymentMethod( {
			api: WCPayAPI(),
			emailId: 'billing_email',
		} );

		expect( stripeLinkButton.style.display ).toBe( 'block' );
		expect( stripeLinkButton.style.top ).not.toBe( '' );

		stripeLinkButton.click();
		expect( addEventListenerSpy ).toHaveBeenCalledWith(
			'click',
			expect.any( Function )
		);
		expect(
			WCPayAPI().getStripe().linkAutofillModal().launch
		).toHaveBeenCalledWith( { email: billingEmail } );
	} );

	function createStripeLinkElements() {
		// Create the input field
		const billingEmailInput = document.createElement( 'input' );
		billingEmailInput.setAttribute( 'type', 'email' );
		billingEmailInput.setAttribute( 'id', 'billing_email' );
		billingEmailInput.setAttribute( 'value', billingEmail );

		// Create the button
		const stripeLinkButton = document.createElement( 'button' );
		stripeLinkButton.setAttribute(
			'class',
			'wcpay-stripelink-modal-trigger'
		);
		stripeLinkButton.setAttribute( 'style', 'display:none' );

		// Append the input field and button to the DOM
		document.body.appendChild( billingEmailInput );
		document.body.appendChild( stripeLinkButton );
	}
} );
