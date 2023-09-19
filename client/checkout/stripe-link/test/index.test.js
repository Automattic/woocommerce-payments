/**
 * Internal dependencies
 */
import enableStripeLinkPaymentMethod, { autofill } from '..';
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

	test( 'Custom fill function should be called', () => {
		const customFillFunction = jest.fn();
		autofill(
			{
				value: {
					billingAddress: '123 Main St',
					shippingAddress: {
						name: 'First Last',
						address: {
							line1: '123 Main St',
							line2: 'shipping',
							state: 'AK',
							country: 'US',
							city: 'San Francisco',
							postal_code: '94110',
						},
					},
				},
			},
			{
				fill_field_method: customFillFunction,
				complete_shipping: () => {
					return true;
				},
				complete_billing: () => {
					return false;
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
			}
		);
		expect( customFillFunction ).toHaveBeenCalled();
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

describe( 'Autofilling billing fields', () => {
	beforeEach( () => {
		createEmptyBillingFields();
	} );

	afterEach( () => {
		resetBillingFields();
	} );

	test( 'Should fill the billing fields when complete_billing is true', () => {
		autofill(
			{
				value: {
					billingAddress: {
						name: 'First Last',
						address: {
							line1: '123 Main St',
							line2: 'shipping',
							state: 'AK',
							country: 'US',
							city: 'San Francisco',
							postal_code: '94110',
						},
					},
				},
			},
			{
				complete_shipping: () => {
					return false;
				},
				complete_billing: () => {
					return true;
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
			}
		);

		expect( document.getElementById( 'billing_first_name' ).value ).toBe(
			'First'
		);
		expect( document.getElementById( 'billing_last_name' ).value ).toBe(
			'Last'
		);
		expect( document.getElementById( 'billing_address_1' ).value ).toBe(
			'123 Main St'
		);
		expect( document.getElementById( 'billing_address_2' ).value ).toBe(
			'shipping'
		);
		expect( document.getElementById( 'billing_city' ).value ).toBe(
			'San Francisco'
		);
		expect( document.getElementById( 'billing_state' ).value ).toBe( 'AK' );
		expect( document.getElementById( 'billing_postcode' ).value ).toBe(
			'94110'
		);
		expect( document.getElementById( 'billing_country' ).value ).toBe(
			'US'
		);
	} );

	test( 'Should not fill the billing fields when complete_billing is false', () => {
		autofill(
			{
				value: {},
			},
			{
				complete_shipping: () => {
					return false;
				},
				complete_billing: () => {
					return false;
				},
			}
		);

		expect( document.getElementById( 'billing_first_name' ).value ).toBe(
			''
		);
		expect( document.getElementById( 'billing_last_name' ).value ).toBe(
			''
		);
		expect( document.getElementById( 'billing_address_1' ).value ).toBe(
			''
		);
		expect( document.getElementById( 'billing_address_2' ).value ).toBe(
			''
		);
		expect( document.getElementById( 'billing_city' ).value ).toBe( '' );
		expect( document.getElementById( 'billing_state' ).value ).toBe( '' );
		expect( document.getElementById( 'billing_postcode' ).value ).toBe(
			''
		);
		expect( document.getElementById( 'billing_country' ).value ).toBe( '' );
	} );

	function createEmptyBillingFields() {
		const firstName = document.createElement( 'input' );
		firstName.setAttribute( 'id', 'billing_first_name' );

		const lastName = document.createElement( 'input' );
		lastName.setAttribute( 'id', 'billing_last_name' );

		const address1 = document.createElement( 'input' );
		address1.setAttribute( 'id', 'billing_address_1' );

		const address2 = document.createElement( 'input' );
		address2.setAttribute( 'id', 'billing_address_2' );

		const city = document.createElement( 'input' );
		city.setAttribute( 'id', 'billing_city' );

		const state = document.createElement( 'input' );
		state.setAttribute( 'id', 'billing_state' );

		const postcode = document.createElement( 'input' );
		postcode.setAttribute( 'id', 'billing_postcode' );

		const country = document.createElement( 'input' );
		country.setAttribute( 'id', 'billing_country' );

		document.body.appendChild( firstName );
		document.body.appendChild( lastName );
		document.body.appendChild( address1 );
		document.body.appendChild( address2 );
		document.body.appendChild( city );
		document.body.appendChild( state );
		document.body.appendChild( postcode );
		document.body.appendChild( country );
	}

	function resetBillingFields() {
		const firstName = document.querySelector( '#billing_first_name' );
		const lastName = document.querySelector( '#billing_last_name' );
		const address1 = document.querySelector( '#billing_address_1' );
		const address2 = document.querySelector( '#billing_address_2' );
		const city = document.querySelector( '#billing_city' );
		const state = document.querySelector( '#billing_state' );
		const postcode = document.querySelector( '#billing_postcode' );
		const country = document.querySelector( '#billing_country' );

		firstName.value = '';
		lastName.value = '';
		address1.value = '';
		address2.value = '';
		city.value = '';
		state.value = '';
		postcode.value = '';
		country.value = '';
	}
} );

describe( 'Autofilling shipping fields', () => {
	beforeEach( () => {
		createEmptyShippingFields();
	} );

	afterEach( () => {
		resetShippingFields();
	} );

	test( 'Should fill the shipping fields when complete_shipping is true', () => {
		autofill(
			{
				value: {
					billingAddress: '123 Main St',
					shippingAddress: {
						name: 'First Last',
						address: {
							line1: '123 Main St',
							line2: 'shipping',
							state: 'AK',
							country: 'US',
							city: 'San Francisco',
							postal_code: '94110',
						},
					},
				},
			},
			{
				complete_shipping: () => {
					return true;
				},
				complete_billing: () => {
					return false;
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
			}
		);

		expect( document.getElementById( 'shipping_first_name' ).value ).toBe(
			'First'
		);
		expect( document.getElementById( 'shipping_last_name' ).value ).toBe(
			'Last'
		);
		expect( document.getElementById( 'shipping_address_1' ).value ).toBe(
			'123 Main St'
		);
		expect( document.getElementById( 'shipping_address_2' ).value ).toBe(
			'shipping'
		);
		expect( document.getElementById( 'shipping_city' ).value ).toBe(
			'San Francisco'
		);
		expect( document.getElementById( 'shipping_state' ).value ).toBe(
			'AK'
		);
		expect( document.getElementById( 'shipping_postcode' ).value ).toBe(
			'94110'
		);
		expect( document.getElementById( 'shipping_country' ).value ).toBe(
			'US'
		);
	} );

	test( 'Should not fill the shipping fields when complete_shipping is false', () => {
		autofill(
			{
				value: {},
			},
			{
				complete_shipping: () => {
					return false;
				},
				complete_billing: () => {
					return false;
				},
			}
		);

		expect( document.getElementById( 'shipping_first_name' ).value ).toBe(
			''
		);
		expect( document.getElementById( 'shipping_last_name' ).value ).toBe(
			''
		);
		expect( document.getElementById( 'shipping_address_1' ).value ).toBe(
			''
		);
		expect( document.getElementById( 'shipping_address_2' ).value ).toBe(
			''
		);
		expect( document.getElementById( 'shipping_city' ).value ).toBe( '' );
		expect( document.getElementById( 'shipping_state' ).value ).toBe( '' );
		expect( document.getElementById( 'shipping_postcode' ).value ).toBe(
			''
		);
		expect( document.getElementById( 'shipping_country' ).value ).toBe(
			''
		);
	} );

	function createEmptyShippingFields() {
		const firstName = document.createElement( 'input' );
		firstName.setAttribute( 'id', 'shipping_first_name' );

		const lastName = document.createElement( 'input' );
		lastName.setAttribute( 'id', 'shipping_last_name' );

		const address1 = document.createElement( 'input' );
		address1.setAttribute( 'id', 'shipping_address_1' );

		const address2 = document.createElement( 'input' );
		address2.setAttribute( 'id', 'shipping_address_2' );

		const city = document.createElement( 'input' );
		city.setAttribute( 'id', 'shipping_city' );

		const state = document.createElement( 'input' );
		state.setAttribute( 'id', 'shipping_state' );

		const postcode = document.createElement( 'input' );
		postcode.setAttribute( 'id', 'shipping_postcode' );

		const country = document.createElement( 'input' );
		country.setAttribute( 'id', 'shipping_country' );

		document.body.appendChild( firstName );
		document.body.appendChild( lastName );
		document.body.appendChild( address1 );
		document.body.appendChild( address2 );
		document.body.appendChild( city );
		document.body.appendChild( state );
		document.body.appendChild( postcode );
		document.body.appendChild( country );
	}

	function resetShippingFields() {
		const firstName = document.querySelector( '#shipping_first_name' );
		const lastName = document.querySelector( '#shipping_last_name' );
		const address1 = document.querySelector( '#shipping_address_1' );
		const address2 = document.querySelector( '#shipping_address_2' );
		const city = document.querySelector( '#shipping_city' );
		const state = document.querySelector( '#shipping_state' );
		const postcode = document.querySelector( '#shipping_postcode' );
		const country = document.querySelector( '#shipping_country' );

		firstName.value = '';
		lastName.value = '';
		address1.value = '';
		address2.value = '';
		city.value = '';
		state.value = '';
		postcode.value = '';
		country.value = '';
	}
} );
