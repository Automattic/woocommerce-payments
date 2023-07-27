/**
 * Internal dependencies
 */
import {
	createAndConfirmSetupIntent,
	mountStripePaymentElement,
	processPayment,
	renderTerms,
} from '../payment-processing';
import { getAppearance } from '../../../upe-styles';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { getFingerprint } from 'wcpay/checkout/utils/fingerprint';
import showErrorCheckout from 'wcpay/checkout/utils/show-error-checkout';
import { waitFor } from '@testing-library/react';
import { getSelectedUPEGatewayPaymentMethod } from 'wcpay/checkout/utils/upe';

jest.mock( '../../../upe-styles' );

jest.mock( 'wcpay/checkout/utils/upe' );

jest.mock( 'wcpay/utils/checkout', () => {
	return {
		getUPEConfig: jest.fn( ( argument ) => {
			if ( argument === 'paymentMethodsConfig' ) {
				return {
					card: {
						label: 'Card',
						forceNetworkSavedCards: true,
					},
					giropay: {
						label: 'Giropay',
						forceNetworkSavedCards: false,
					},
					ideal: {
						label: 'iDEAL',
						forceNetworkSavedCards: false,
					},
					sepa: {
						label: 'SEPA',
						forceNetworkSavedCards: false,
					},
				};
			}

			if ( argument === 'currency' ) {
				return 'eur';
			}
		} ),
		getConfig: jest.fn(),
	};
} );

jest.mock( 'wcpay/checkout/utils/fingerprint', () => {
	return {
		getFingerprint: jest.fn(),
	};
} );

jest.mock( 'wcpay/checkout/utils/show-error-checkout', () => {
	return jest.fn();
} );

const mockUpdateFunction = jest.fn();

const mockMountFunction = jest.fn();

const mockCreateFunction = jest.fn( () => {
	return {
		mount: mockMountFunction,
		update: mockUpdateFunction,
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

const mockGetStripeForUPE = jest.fn( () => {
	return {
		elements: mockElements,
		createPaymentMethod: mockCreatePaymentMethod,
	};
} );

const saveUPEAppearanceMock = jest.fn();

const mockSetupIntentThen = jest.fn();
const setupIntentMock = jest.fn( () => {
	return {
		then: mockSetupIntentThen,
	};
} );

const apiMock = {
	saveUPEAppearance: saveUPEAppearanceMock,
	getStripeForUPE: mockGetStripeForUPE,
	setupIntent: setupIntentMock,
};

describe( 'Stripe Payment Element mounting', () => {
	let mockDomElement;

	beforeEach( () => {
		mockDomElement = document.createElement( 'div' );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'initializes the appearance when it is not set and saves it', async () => {
		// Create a mock function to track the event dispatch for tokenization-form.js execution
		const dispatchMock = jest.fn();
		document.body.dispatchEvent = dispatchMock;

		const appearanceMock = { backgroundColor: '#fff' };
		getAppearance.mockReturnValue( appearanceMock );
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		mockDomElement.dataset.paymentMethodType = 'giropay';

		mountStripePaymentElement( apiMock, mockDomElement );

		await waitFor( () => {
			expect( getAppearance ).toHaveBeenCalled();
			expect( apiMock.saveUPEAppearance ).toHaveBeenCalledWith(
				appearanceMock
			);
			expect( dispatchMock ).toHaveBeenCalled();
		} );
	} );

	test( 'does not call getAppearance or saveUPEAppearance if appearance is already set', async () => {
		const appearanceMock = { backgroundColor: '#fff' };
		getAppearance.mockReturnValue( appearanceMock );
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );
		getUPEConfig.mockImplementation( ( argument ) => {
			if ( argument === 'currency' ) {
				return 'eur';
			}

			if ( argument === 'upeAppearance' ) {
				return {
					backgroundColor: '#fff',
				};
			}

			if ( argument === 'paymentMethodsConfig' ) {
				return {
					ideal: {
						label: 'iDEAL',
						forceNetworkSavedCards: false,
					},
					card: {
						label: 'Card',
						forceNetworkSavedCards: true,
					},
					giropay: {
						label: 'Giropay',
						forceNetworkSavedCards: false,
					},
					sepa: {
						label: 'SEPA',
						forceNetworkSavedCards: false,
					},
				};
			}
		} );

		mockDomElement.dataset.paymentMethodType = 'giropay';

		mountStripePaymentElement( apiMock, mockDomElement );

		await waitFor( () => {
			expect( getAppearance ).not.toHaveBeenCalled();
			expect( apiMock.saveUPEAppearance ).not.toHaveBeenCalled();
		} );
	} );

	test( 'Prevents from mounting when no figerprint is available', async () => {
		getFingerprint.mockImplementation( () => {
			throw new Error( 'No fingerprint' );
		} );

		mountStripePaymentElement( apiMock, null );

		await waitFor( () => {
			expect( showErrorCheckout ).toHaveBeenCalledWith(
				'No fingerprint'
			);
			expect( apiMock.getStripeForUPE ).not.toHaveBeenCalled();
			expect( mockElements ).not.toHaveBeenCalled();
			expect( mockCreateFunction ).not.toHaveBeenCalled();
		} );
	} );

	test( 'upeElement is created and mounted when fingerprint is available', async () => {
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		mockDomElement.dataset.paymentMethodType = 'card';

		mountStripePaymentElement( apiMock, mockDomElement );

		await waitFor( () => {
			expect( apiMock.getStripeForUPE ).toHaveBeenCalled();
			expect( mockElements ).toHaveBeenCalled();
			expect( mockCreateFunction ).toHaveBeenCalled();
			expect( mockMountFunction ).toHaveBeenCalled();
		} );
	} );

	test( 'Terms are rendered for an already mounted element which should be saved', () => {
		const event = {
			target: {
				checked: true,
			},
		};
		getUPEConfig.mockImplementation( ( argument ) => {
			if ( argument === 'currency' ) {
				return 'eur';
			}

			if ( argument === 'isUPEEnabled' ) {
				return true;
			}
		} );

		getSelectedUPEGatewayPaymentMethod.mockReturnValue( 'card' );
		renderTerms( event );
		expect( getUPEConfig ).toHaveBeenCalledWith( 'isUPEEnabled' );
		expect( mockUpdateFunction ).toHaveBeenCalled();
	} );

	test( 'Terms are not rendered when no selected payment method is found', () => {
		const event = {
			target: {
				checked: true,
			},
		};
		getSelectedUPEGatewayPaymentMethod.mockReturnValue( null );
		renderTerms( event );
		expect( getUPEConfig ).not.toHaveBeenCalledWith( 'isUPEEnabled' );
		expect( mockUpdateFunction ).not.toHaveBeenCalled();
	} );

	test( 'existing upeElement is not created again but instead mounted immediately when fingerprint is available', async () => {
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		getUPEConfig.mockImplementation( ( argument ) => {
			if ( argument === 'currency' ) {
				return 'eur';
			}

			if ( argument === 'paymentMethodsConfig' ) {
				return {
					sepa: {
						label: 'SEPA',
						forceNetworkSavedCards: false,
					},
				};
			}
		} );

		mockDomElement.dataset.paymentMethodType = 'sepa';

		mountStripePaymentElement( apiMock, mockDomElement );
		mountStripePaymentElement( apiMock, mockDomElement );

		await waitFor( () => {
			expect( apiMock.getStripeForUPE ).toHaveBeenCalled();
			expect( mockElements ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );

describe( 'Payment processing', () => {
	beforeEach( () => {
		getUPEConfig.mockImplementation( ( argument ) => {
			if ( argument === 'currency' ) {
				return 'eur';
			}

			if ( argument === 'paymentMethodsConfig' ) {
				return {
					card: {
						label: 'card',
						forceNetworkSavedCards: false,
					},
				};
			}
		} );
	} );
	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'Successful payment processing', async () => {
		setupBillingDetailsFields();
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		const mockDomElement = document.createElement( 'div' );
		mockDomElement.dataset.paymentMethodType = 'card';

		await mountStripePaymentElement( apiMock, mockDomElement );

		const mockJqueryForm = {
			submit: jest.fn(),
			addClass: jest.fn( () => {
				return {
					block: jest.fn(),
				};
			} ),
			removeClass: jest.fn(),
			unblock: jest.fn(),
			attr: jest.fn().mockReturnValue( 'checkout' ),
		};

		const checkoutResult = processPayment(
			apiMock,
			mockJqueryForm,
			'card'
		);

		expect( mockJqueryForm.addClass ).toHaveBeenCalledWith( 'processing' );
		expect( mockJqueryForm.removeClass ).not.toHaveBeenCalledWith(
			'processing'
		);
		await waitFor( () => {
			expect( mockSubmit ).toHaveBeenCalled();
		} );
		expect( mockCreatePaymentMethod ).toHaveBeenCalled();
		expect( mockThen ).toHaveBeenCalled();
		expect( checkoutResult ).toBe( false );
	} );

	test( 'Payment processing adds billing details for checkout', async () => {
		setupBillingDetailsFields();
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		const mockDomElement = document.createElement( 'div' );
		mockDomElement.dataset.paymentMethodType = 'card';

		await mountStripePaymentElement( apiMock, mockDomElement );

		const checkoutForm = {
			submit: jest.fn(),
			addClass: jest.fn( () => {
				return {
					block: jest.fn(),
				};
			} ),
			removeClass: jest.fn(),
			unblock: jest.fn(),
			attr: jest.fn().mockReturnValue( 'checkout' ),
		};

		await processPayment( apiMock, checkoutForm, 'card' );

		expect( mockCreatePaymentMethod ).toHaveBeenCalledWith( {
			elements: expect.any( Object ),
			params: {
				billing_details: expect.any( Object ),
			},
		} );
	} );

	test( 'Payment processing does not add billing details for non-checkout forms', async () => {
		setupBillingDetailsFields();
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		const mockDomElement = document.createElement( 'div' );
		mockDomElement.dataset.paymentMethodType = 'card';

		await mountStripePaymentElement( apiMock, mockDomElement );

		const addPaymentMethodForm = {
			submit: jest.fn(),
			addClass: jest.fn( () => {
				return {
					block: jest.fn(),
				};
			} ),
			removeClass: jest.fn(),
			unblock: jest.fn(),
			attr: jest.fn().mockReturnValue( 'add_payment_method' ),
		};

		await processPayment( apiMock, addPaymentMethodForm, 'card' );

		expect( mockCreatePaymentMethod ).toHaveBeenCalledWith( {
			elements: expect.any( Object ),
			params: {},
		} );
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

describe( 'Setup intent creation and confirmation', () => {
	test( 'Setup intent is created and confirmed', async () => {
		const mockDomElement = document.createElement( 'div' );
		mockDomElement.dataset.paymentMethodType = 'card';

		const mockJqueryForm = {
			submit: jest.fn(),
			addClass: jest.fn( () => {
				return {
					block: jest.fn(),
				};
			} ),
			removeClass: jest.fn(),
			unblock: jest.fn(),
			attr: jest.fn().mockReturnValue( 'add_payment_method' ),
		};

		await createAndConfirmSetupIntent(
			mockJqueryForm,
			{ id: 'si123xyz' },
			apiMock
		);

		expect( setupIntentMock ).toHaveBeenCalled();
	} );
} );
