/**
 * Internal dependencies
 */
import {
	checkout,
	initializeAppearance,
	inititalizeStripeElements,
	mountStripePaymentElement,
} from '../stripe-checkout';
import { getAppearance } from '../../../upe-styles';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { getFingerprint } from 'wcpay/checkout/utils/fingerprint';
import showErrorCheckout from 'wcpay/checkout/utils/show-error-checkout';
import { waitFor } from '@testing-library/react';
import WCPayAPI from 'wcpay/checkout/api';
// const appppi = require('wcpay/checkout/api');

jest.mock( '../../../upe-styles' );

jest.mock( 'wcpay/utils/checkout', () => {
	return {
		getUPEConfig: jest.fn(),
		getConfig: jest.fn(),
	};
} );

jest.mock( 'wcpay/checkout/api', () => {
	const mockMountFunction = jest.fn();

	const mockCreateFunction = jest.fn( () => {
		return {
			mount: mockMountFunction,
		};
	} );

	const mockSubmit = jest.fn( () => {
		return {
			then: jest.fn(),
		};
	} );

	const mockElements = jest.fn( () => {
		return {
			create: mockCreateFunction,
			submit: mockSubmit,
		};
	} );

	const mockThen = jest.fn( () => {
		return {
			catch: jest.fn(),
		};
	} );

	const mockCreatePaymentMethod = jest.fn( () => {
		return {
			then: mockThen,
		};
	} );

	const mockGetStripe = jest.fn( () => {
		return {
			elements: mockElements,
			createPaymentMethod: mockCreatePaymentMethod,
		};
	} );

	const saveUPEAppearanceMock = jest.fn();

	return jest.fn().mockImplementation( () => {
		return {
			saveUPEAppearance: saveUPEAppearanceMock,
			getStripe: mockGetStripe,
		};
	} );
} );

jest.mock( 'wcpay/checkout/utils/fingerprint', () => {
	return {
		getFingerprint: jest.fn(),
	};
} );

jest.mock( 'wcpay/checkout/utils/show-error-checkout', () => {
	return jest.fn();
} );

describe( 'UPE appearance initialization', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'initializes the appearance when it is not set and saves it', () => {
		const mockAppearance = { backgroundColor: '#fff' };
		getAppearance.mockReturnValue( mockAppearance );

		initializeAppearance();

		expect( getAppearance ).toHaveBeenCalled();
		expect( WCPayAPI().saveUPEAppearance ).toHaveBeenCalled();
	} );

	test( 'does not call getAppearance or saveUPEAppearance if appearance is already set', () => {
		const mockAppearance = { backgroundColor: '#fff' };
		getAppearance.mockReturnValue( mockAppearance );
		getUPEConfig.mockImplementation( () => {
			return {
				upeAppearance: { backgroundColor: '#fff' },
			};
		} );
		inititalizeStripeElements();

		initializeAppearance();

		expect( getAppearance ).not.toHaveBeenCalled();
		expect( WCPayAPI().saveUPEAppearance ).not.toHaveBeenCalled();
	} );
} );

describe( 'Mount Stripe Payment Element', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'Prevents from mounting when no figerprint is available', async () => {
		getFingerprint.mockImplementation( () => {
			throw new Error( 'No fingerprint' );
		} );

		mountStripePaymentElement( null );

		await waitFor( () => {
			expect( showErrorCheckout ).toHaveBeenCalledWith(
				'No fingerprint'
			);
			expect( WCPayAPI().getStripe ).not.toHaveBeenCalled();
			expect( WCPayAPI().getStripe().elements ).not.toHaveBeenCalled();
			expect(
				WCPayAPI().getStripe().elements().create
			).not.toHaveBeenCalled();
		} );
	} );

	test( 'upeElement is created and mounted when fingerprint is available', async () => {
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		getUPEConfig.mockImplementation( ( argument ) => {
			if ( 'paymentMethodsConfig' === argument ) {
				return {
					card: {
						label: 'Card',
					},
				};
			}

			if ( 'currency' === argument ) {
				return 'eur';
			}
		} );

		const mockDomElement = document.createElement( 'div' );
		mockDomElement.dataset.paymentMethodType = 'card';
		inititalizeStripeElements();

		mountStripePaymentElement( mockDomElement );

		await waitFor( () => {
			expect( WCPayAPI().getStripe ).toHaveBeenCalled();
			expect( WCPayAPI().getStripe().elements ).toHaveBeenCalled();
			expect(
				WCPayAPI().getStripe().elements().create
			).toHaveBeenCalled();
			expect(
				WCPayAPI().getStripe().elements().create().mount
			).toHaveBeenCalled();
		} );
	} );

	test( 'existing upeElement is not created again but instead mounted immediately when fingerprint is available', async () => {
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		getUPEConfig.mockImplementation( ( argument ) => {
			if ( 'paymentMethodsConfig' === argument ) {
				return {
					card: {
						label: 'Card',
					},
				};
			}

			if ( 'currency' === argument ) {
				return 'eur';
			}
		} );

		const mockDomElement = document.createElement( 'div' );
		mockDomElement.dataset.paymentMethodType = 'card';
		inititalizeStripeElements();

		mountStripePaymentElement( mockDomElement );
		mountStripePaymentElement( mockDomElement );

		await waitFor( () => {
			expect(
				WCPayAPI().getStripe().elements().create
			).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );

describe( 'Checkout', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'Successful checkout', async () => {
		setupBillingDetailsFields();
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		getUPEConfig.mockImplementation( ( argument ) => {
			if ( 'paymentMethodsConfig' === argument ) {
				return {
					card: {
						label: 'Card',
					},
				};
			}

			if ( 'currency' === argument ) {
				return 'eur';
			}
		} );

		const mockDomElement = document.createElement( 'div' );
		mockDomElement.dataset.paymentMethodType = 'card';
		inititalizeStripeElements();

		await mountStripePaymentElement( mockDomElement );

		const mockJqueryForm = {
			submit: jest.fn(),
			addClass: jest.fn( () => {
				return {
					block: jest.fn(),
				};
			} ),
			removeClass: jest.fn(),
			unblock: jest.fn(),
		};

		const checkoutResult = checkout( mockJqueryForm, 'card' );

		expect( mockJqueryForm.addClass ).toHaveBeenCalledWith( 'processing' );
		expect( WCPayAPI().getStripe().elements().submit ).toHaveBeenCalled();
		expect( mockJqueryForm.removeClass ).not.toHaveBeenCalledWith(
			'processing'
		);
		expect( WCPayAPI().getStripe().createPaymentMethod ).toHaveBeenCalled();
		expect(
			WCPayAPI().getStripe().createPaymentMethod().then
		).toHaveBeenCalled();
		expect(
			WCPayAPI().getStripe().createPaymentMethod().then().catch
		).not.toHaveBeenCalled();
		expect( checkoutResult ).toBe( false );
	} );

	function setupBillingDetailsFields() {
		// Create DOM elements for the test
		const firstNameInput = document.createElement( 'input' );
		firstNameInput.id = 'billing_first_name';
		firstNameInput.value = 'John';

		const lastNameInput = document.createElement( 'input' );
		lastNameInput.id = 'billing_last_name';
		lastNameInput.value = 'Doe';

		const emailInput = document.createElement( 'input' );
		emailInput.id = 'billing_email';
		emailInput.value = 'john.doe@example.com';

		const phoneInput = document.createElement( 'input' );
		phoneInput.id = 'billing_phone';
		phoneInput.value = '555-1234';

		const cityInput = document.createElement( 'input' );
		cityInput.id = 'billing_city';
		cityInput.value = 'New York';

		const countryInput = document.createElement( 'input' );
		countryInput.id = 'billing_country';
		countryInput.value = 'US';

		const address1Input = document.createElement( 'input' );
		address1Input.id = 'billing_address_1';
		address1Input.value = '123 Main St';

		const address2Input = document.createElement( 'input' );
		address2Input.id = 'billing_address_2';
		address2Input.value = '';

		const postcodeInput = document.createElement( 'input' );
		postcodeInput.id = 'billing_postcode';
		postcodeInput.value = '10001';

		const stateInput = document.createElement( 'input' );
		stateInput.id = 'billing_state';
		stateInput.value = 'NY';

		// Add the DOM elements to the document
		document.body.appendChild( firstNameInput );
		document.body.appendChild( lastNameInput );
		document.body.appendChild( emailInput );
		document.body.appendChild( phoneInput );
		document.body.appendChild( cityInput );
		document.body.appendChild( countryInput );
		document.body.appendChild( address1Input );
		document.body.appendChild( address2Input );
		document.body.appendChild( postcodeInput );
		document.body.appendChild( stateInput );
	}
} );
