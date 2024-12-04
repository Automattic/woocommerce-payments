/**
 * Internal dependencies
 */
import enableStripeLinkPaymentMethod from '..';
import WCPayAPI from 'wcpay/checkout/api';

const mockOn = jest.fn();
const mockLaunch = jest.fn();

const mockLaunchAutofillModal = jest.fn( () => {
	return {
		launch: mockLaunch,
		on: mockOn,
	};
} );

const mockedStripe = jest.fn( () => {
	return Promise.resolve( {
		linkAutofillModal: mockLaunchAutofillModal,
	} );
} );

jest.mock( 'wcpay/checkout/api', () =>
	jest.fn().mockImplementation( () => ( {
		getStripe: mockedStripe,
	} ) )
);

const billingEmail = 'example@example.com';

describe( 'Stripe Link elements behavior', () => {
	test( 'Should stop if emailId is not found', async () => {
		await enableStripeLinkPaymentMethod( {
			emailId: 'not_existing_email@example.com',
		} );
		expect( mockLaunchAutofillModal ).not.toHaveBeenCalled();
	} );

	test( 'Should call linkAutofillModal when email is present', async () => {
		createStripeLinkElements();
		await enableStripeLinkPaymentMethod( {
			api: WCPayAPI(),
			emailId: 'billing_email',
			onAutofill: () => null,
			onButtonShow: () => null,
		} );
		expect( mockLaunchAutofillModal ).toHaveBeenCalled();
	} );

	test( 'Should add keyup event listener to email input', async () => {
		createStripeLinkElements();
		const billingEmailInput = document.getElementById( 'billing_email' );
		const addEventListenerSpy = jest.spyOn(
			billingEmailInput,
			'addEventListener'
		);

		await enableStripeLinkPaymentMethod( {
			api: WCPayAPI(),
			emailId: 'billing_email',
			onAutofill: () => null,
			onButtonShow: () => null,
		} );

		billingEmailInput.dispatchEvent( new Event( 'keyup' ) );
		expect( addEventListenerSpy ).toHaveBeenCalledWith(
			'keyup',
			expect.any( Function )
		);
		expect( mockLaunch ).toHaveBeenCalledWith( {
			email: billingEmail,
		} );
	} );

	test( 'Stripe Link button should call onButtonShow configuration value', async () => {
		createStripeLinkElements();
		const handleButtonShow = jest.fn();
		await enableStripeLinkPaymentMethod( {
			api: WCPayAPI(),
			emailId: 'billing_email',
			onAutofill: () => null,
			onButtonShow: handleButtonShow,
		} );

		expect( handleButtonShow ).toHaveBeenCalled();
	} );

	test( 'Should properly clean up when cleanup function is called', async () => {
		createStripeLinkElements();
		const billingEmailInput = document.getElementById( 'billing_email' );
		const removeEventListenerSpy = jest.spyOn(
			billingEmailInput,
			'removeEventListener'
		);
		const removeLinkButtonSpy = jest.spyOn(
			document.querySelector( '.wcpay-stripelink-modal-trigger' ),
			'remove'
		);

		const cleanup = await enableStripeLinkPaymentMethod( {
			api: WCPayAPI(),
			emailId: 'billing_email',
			onAutofill: () => null,
			onButtonShow: () => null,
		} );

		// Call the cleanup function
		cleanup();

		expect( removeEventListenerSpy ).toHaveBeenCalledWith(
			'keyup',
			expect.any( Function )
		);
		expect( removeLinkButtonSpy ).toHaveBeenCalled();
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
