/**
 * Internal dependencies
 */
import {
	createAndConfirmSetupIntent,
	mountStripePaymentElement,
	processPayment,
	renderTerms,
	__resetGatewayUPEComponentsElement,
} from '../payment-processing';
import { getAppearance } from '../../upe-styles';
import { getUPEConfig } from 'wcpay/utils/checkout';
import { getFingerprint } from 'wcpay/checkout/utils/fingerprint';
import showErrorCheckout from 'wcpay/checkout/utils/show-error-checkout';
import { waitFor } from '@testing-library/react';
import { getSelectedUPEGatewayPaymentMethod } from 'wcpay/checkout/utils/upe';

jest.mock( '../../upe-styles' );

jest.mock( 'wcpay/checkout/utils/upe' );

jest.mock( 'wcpay/utils/checkout', () => ( {
	getUPEConfig: jest.fn( ( argument ) => {
		if (
			argument === 'wcBlocksUPEAppearance' ||
			argument === 'upeAppearance'
		) {
			return {};
		}

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
} ) );

jest.mock( 'wcpay/checkout/utils/fingerprint', () => ( {
	getFingerprint: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/utils/show-error-checkout', () => jest.fn() );

const mockUpdateFunction = jest.fn();

const mockMountFunction = jest.fn();

let eventHandlersFromElementsCreate = {};
const mockCreateFunction = jest.fn( () => ( {
	mount: mockMountFunction,
	update: mockUpdateFunction,
	on: ( event, handler ) => {
		if ( ! eventHandlersFromElementsCreate[ event ] ) {
			eventHandlersFromElementsCreate[ event ] = [];
		}
		eventHandlersFromElementsCreate[ event ].push( handler );
	},
} ) );

const mockSubmit = jest.fn( () => ( {
	then: jest.fn(),
} ) );

const mockElements = jest.fn( () => ( {
	create: mockCreateFunction,
	submit: mockSubmit,
} ) );

const mockCreatePaymentMethod = jest.fn().mockResolvedValue( {} );

const apiMock = {
	saveUPEAppearance: jest.fn().mockResolvedValue( {} ),
	getStripeForUPE: jest.fn( () => ( {
		elements: mockElements,
		createPaymentMethod: mockCreatePaymentMethod,
	} ) ),
	setupIntent: jest.fn().mockResolvedValue( {} ),
};

describe( 'Stripe Payment Element mounting', () => {
	let mockDomElement;

	beforeEach( () => {
		mockDomElement = document.createElement( 'div' );
		eventHandlersFromElementsCreate = {};
		getUPEConfig.mockImplementation( ( argument ) => {
			if (
				argument === 'wcBlocksUPEAppearance' ||
				argument === 'upeAppearance'
			) {
				return {};
			}

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
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	[
		{
			elementsLocation: 'shortcode_checkout',
			expectedProperty: 'upeAppearance',
		},
		{
			elementsLocation: 'add_payment_method',
			expectedProperty: 'upeAddPaymentMethodAppearance',
		},
		{
			elementsLocation: 'other',
			expectedProperty: 'upeAppearance',
		},
	].forEach( ( { elementsLocation, expectedProperty } ) => {
		describe( `when elementsLocation is ${ elementsLocation }`, () => {
			beforeEach( () => {
				__resetGatewayUPEComponentsElement( 'giropay' );
			} );

			test( 'initializes the appearance when it is not set and saves it', async () => {
				getUPEConfig.mockImplementation( ( argument ) => {
					if (
						argument === 'upeAddPaymentMethodAppearance' ||
						argument === 'upeAppearance'
					) {
						return null;
					}

					if ( argument === 'paymentMethodsConfig' ) {
						return {
							giropay: {
								label: 'Giropay',
								forceNetworkSavedCards: false,
							},
						};
					}

					if ( argument === 'currency' ) {
						return 'eur';
					}
				} );

				// Create a mock function to track the event dispatch for tokenization-form.js execution
				const dispatchMock = jest.fn();
				document.body.dispatchEvent = dispatchMock;

				const appearanceMock = { backgroundColor: '#fff' };
				getAppearance.mockReturnValue( appearanceMock );
				getFingerprint.mockImplementation( () => {
					return 'fingerprint';
				} );

				mockDomElement.dataset.paymentMethodType = 'giropay';

				await mountStripePaymentElement(
					apiMock,
					mockDomElement,
					elementsLocation
				);

				expect( getAppearance ).toHaveBeenCalled();
				expect( apiMock.saveUPEAppearance ).toHaveBeenCalledWith(
					appearanceMock,
					elementsLocation
				);
				expect( getUPEConfig ).toHaveBeenCalledWith( expectedProperty );
				expect( dispatchMock ).toHaveBeenCalled();
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

					if (
						argument === 'upeAppearance' ||
						argument === 'upeAddPaymentMethodAppearance'
					) {
						return {
							backgroundColor: '#fff',
						};
					}

					if ( argument === 'paymentMethodsConfig' ) {
						return {
							giropay: {
								label: 'Giropay',
								forceNetworkSavedCards: false,
							},
						};
					}
				} );

				mockDomElement.dataset.paymentMethodType = 'giropay';

				await mountStripePaymentElement(
					apiMock,
					mockDomElement,
					elementsLocation
				);

				expect( getUPEConfig ).toHaveBeenCalledWith( expectedProperty );
				expect( getAppearance ).not.toHaveBeenCalled();
				expect( apiMock.saveUPEAppearance ).not.toHaveBeenCalled();
			} );
		} );
	} );

	test( 'Prevents from mounting when no figerprint is available', async () => {
		getFingerprint.mockImplementation( () => {
			throw new Error( 'No fingerprint' );
		} );

		await mountStripePaymentElement( apiMock, null );

		expect( showErrorCheckout ).toHaveBeenCalledWith( 'No fingerprint' );
		expect( apiMock.getStripeForUPE ).not.toHaveBeenCalled();
		expect( mockElements ).not.toHaveBeenCalled();
		expect( mockCreateFunction ).not.toHaveBeenCalled();
	} );

	test( 'upeElement is created and mounted when fingerprint is available', async () => {
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		mockDomElement.dataset.paymentMethodType = 'card';

		await mountStripePaymentElement( apiMock, mockDomElement );

		expect( apiMock.getStripeForUPE ).toHaveBeenCalled();
		expect( mockElements ).toHaveBeenCalled();
		expect( mockCreateFunction ).toHaveBeenCalled();
		expect( mockMountFunction ).toHaveBeenCalled();
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
		} );

		getSelectedUPEGatewayPaymentMethod.mockReturnValue( 'card' );
		renderTerms( event );
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
		expect( mockUpdateFunction ).not.toHaveBeenCalled();
	} );

	test( 'existing upeElement is not created again but instead mounted immediately when fingerprint is available', async () => {
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		getUPEConfig.mockImplementation( ( argument ) => {
			if (
				argument === 'wcBlocksUPEAppearance' ||
				argument === 'upeAppearance'
			) {
				return {};
			}

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

		await mountStripePaymentElement( apiMock, mockDomElement );
		await mountStripePaymentElement( apiMock, mockDomElement );

		expect( apiMock.getStripeForUPE ).toHaveBeenCalled();
		expect( mockElements ).toHaveBeenCalledTimes( 1 );
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
		[
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
			'billing_city',
			'billing_country',
			'billing_address_1',
			'billing_address_2',
			'billing_postcode',
			'billing_state',
		].forEach( ( id ) => {
			const element = document.getElementById( id );
			if ( ! element ) return;

			document.body.removeChild( element );
		} );
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
			addClass: jest.fn( () => ( {
				block: jest.fn(),
			} ) ),
			removeClass: jest.fn( () => ( {
				unblock: jest.fn(),
			} ) ),
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
		expect( checkoutResult ).toBe( false );
	} );

	test( 'Payment processing creates the correct `name` attribute when both last/first name fields are removed', async () => {
		setupBillingDetailsFields();
		// pretending that the customizer removed the billing name field
		document.body.removeChild(
			document.getElementById( 'billing_first_name' )
		);
		document.body.removeChild(
			document.getElementById( 'billing_last_name' )
		);
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		const mockDomElement = document.createElement( 'div' );
		mockDomElement.dataset.paymentMethodType = 'card';

		await mountStripePaymentElement( apiMock, mockDomElement );

		const checkoutForm = {
			submit: jest.fn(),
			addClass: jest.fn( () => ( {
				block: jest.fn(),
			} ) ),
			removeClass: jest.fn( () => ( {
				unblock: jest.fn(),
			} ) ),
			attr: jest.fn().mockReturnValue( 'checkout' ),
		};

		await processPayment( apiMock, checkoutForm, 'card' );
		// Wait for promises to resolve.
		await new Promise( ( resolve ) => setImmediate( resolve ) );

		expect( mockCreatePaymentMethod ).toHaveBeenCalledWith( {
			elements: expect.any( Object ),
			params: {
				billing_details: expect.objectContaining( {
					name: undefined,
					email: 'john.doe@example.com',
					phone: '555-1234',
					address: expect.any( Object ),
				} ),
			},
		} );
	} );

	test( 'Payment processing creates the correct `name` attribute when last name field is removed via customizer', async () => {
		setupBillingDetailsFields();
		// pretending that the customizer removed the billing name field
		document.body.removeChild(
			document.getElementById( 'billing_first_name' )
		);
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		const mockDomElement = document.createElement( 'div' );
		mockDomElement.dataset.paymentMethodType = 'card';

		await mountStripePaymentElement( apiMock, mockDomElement );

		const checkoutForm = {
			submit: jest.fn(),
			addClass: jest.fn( () => ( {
				block: jest.fn(),
			} ) ),
			removeClass: jest.fn( () => ( {
				unblock: jest.fn(),
			} ) ),
			attr: jest.fn().mockReturnValue( 'checkout' ),
		};

		await processPayment( apiMock, checkoutForm, 'card' );
		// Wait for promises to resolve.
		await new Promise( ( resolve ) => setImmediate( resolve ) );

		expect( mockCreatePaymentMethod ).toHaveBeenCalledWith( {
			elements: expect.any( Object ),
			params: {
				billing_details: expect.objectContaining( {
					name: 'Doe',
					email: 'john.doe@example.com',
					phone: '555-1234',
					address: expect.any( Object ),
				} ),
			},
		} );
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
			addClass: jest.fn( () => ( {
				block: jest.fn(),
			} ) ),
			removeClass: jest.fn( () => ( {
				unblock: jest.fn(),
			} ) ),
			attr: jest.fn().mockReturnValue( 'checkout' ),
		};

		await processPayment( apiMock, checkoutForm, 'card' );
		// Wait for promises to resolve.
		await new Promise( ( resolve ) => setImmediate( resolve ) );

		expect( mockCreatePaymentMethod ).toHaveBeenCalledWith( {
			elements: expect.any( Object ),
			params: {
				billing_details: expect.any( Object ),
			},
		} );
	} );

	test( 'Payment processing does not create error when some fields are hidden via customizer', async () => {
		setupBillingDetailsFields();
		// pretending that the customizer removed the billing phone field
		document.body.removeChild( document.getElementById( 'billing_phone' ) );
		getFingerprint.mockImplementation( () => {
			return 'fingerprint';
		} );

		const mockDomElement = document.createElement( 'div' );
		mockDomElement.dataset.paymentMethodType = 'card';

		await mountStripePaymentElement( apiMock, mockDomElement );

		const checkoutForm = {
			submit: jest.fn(),
			addClass: jest.fn( () => ( {
				block: jest.fn(),
			} ) ),
			removeClass: jest.fn( () => ( {
				unblock: jest.fn(),
			} ) ),
			attr: jest.fn().mockReturnValue( 'checkout' ),
		};

		await processPayment( apiMock, checkoutForm, 'card' );
		// Wait for promises to resolve.
		await new Promise( ( resolve ) => setImmediate( resolve ) );

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
			addClass: jest.fn( () => ( {
				block: jest.fn(),
			} ) ),
			removeClass: jest.fn( () => ( {
				unblock: jest.fn(),
			} ) ),
			attr: jest.fn().mockReturnValue( 'add_payment_method' ),
		};

		await processPayment( apiMock, addPaymentMethodForm, 'card' );
		// Wait for promises to resolve.
		await new Promise( ( resolve ) => setImmediate( resolve ) );

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
			append: jest.fn(),
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
			{ id: 'si123xyz' },
			mockJqueryForm,
			apiMock
		);

		expect( apiMock.setupIntent ).toHaveBeenCalled();
	} );
} );
