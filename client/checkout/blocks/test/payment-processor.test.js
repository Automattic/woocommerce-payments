/**
 * External dependencies
 */
import { render, screen, act } from '@testing-library/react';
import { useEffect } from 'react';
/**
 * Internal dependencies
 */
import PaymentProcessor from '../payment-processor';
import { PaymentElement } from '@stripe/react-stripe-js';
import { PAYMENT_METHOD_ERROR } from 'wcpay/checkout/constants';

jest.mock( 'wcpay/checkout/classic/payment-processing', () => ( {
	validateElements: jest.fn().mockResolvedValue(),
} ) );
jest.mock( 'wcpay/checkout/blocks/utils', () => ( {
	useCustomerData: jest.fn().mockReturnValue( { billingAddress: {} } ),
} ) );
jest.mock( '../hooks', () => ( {
	usePaymentCompleteHandler: () => null,
	usePaymentFailHandler: () => null,
} ) );
jest.mock( '@woocommerce/blocks-registry', () => ( {
	getPaymentMethods: () => ( {
		woocommerce_payments: { supports: { showSaveOption: false } },
	} ),
} ) );
jest.mock( '@stripe/react-stripe-js', () => ( {
	PaymentElement: jest.fn(),
	useElements: jest.fn(),
	useStripe: jest.fn(),
} ) );

describe( 'PaymentProcessor', () => {
	let mockApi;
	let mockCreatePaymentMethod;
	beforeEach( () => {
		global.wcpay_upe_config = { paymentMethodsConfig: {} };
		PaymentElement.mockImplementation( () => null );
		mockCreatePaymentMethod = jest
			.fn()
			.mockResolvedValue( { paymentMethod: {} } );
		mockApi = {
			getStripeForUPE: () => ( {
				createPaymentMethod: mockCreatePaymentMethod,
			} ),
		};
	} );

	it( 'should render the test mode instructions while in test mode', () => {
		global.wcpay_upe_config.testMode = true;

		render(
			<PaymentProcessor
				activePaymentMethod="woocommerce_payments"
				api={ mockApi }
				paymentMethodId="card"
				emitResponse={ {} }
				eventRegistration={ { onPaymentSetup: () => undefined } }
				fingerprint=""
				shouldSavePayment={ false }
				testingInstructions="Testing instructions"
				upeMethods={ { card: 'woocommerce_payments' } }
			/>
		);

		expect(
			screen.queryByText( 'Testing instructions' )
		).toBeInTheDocument();
	} );

	it( 'should not render the test mode instructions while not in test mode', () => {
		global.wcpay_upe_config.testMode = false;

		render(
			<PaymentProcessor
				activePaymentMethod="woocommerce_payments"
				api={ mockApi }
				paymentMethodId="card"
				emitResponse={ {} }
				eventRegistration={ { onPaymentSetup: () => undefined } }
				fingerprint=""
				shouldSavePayment={ false }
				testingInstructions="Testing instructions"
				upeMethods={ { card: 'woocommerce_payments' } }
			/>
		);

		expect(
			screen.queryByText( 'Testing instructions' )
		).not.toBeInTheDocument();
	} );

	it( 'should return an error if the payment method could not be loaded', async () => {
		PaymentElement.mockImplementation( ( { onLoadError } ) => {
			useEffect( () => {
				onLoadError();
			}, [ onLoadError ] );

			return null;
		} );
		let onPaymentSetupCallback;
		render(
			<PaymentProcessor
				activePaymentMethod="woocommerce_payments"
				api={ mockApi }
				paymentMethodId="card"
				emitResponse={ {} }
				eventRegistration={ {
					onPaymentSetup: ( callback ) =>
						( onPaymentSetupCallback = callback ),
				} }
				fingerprint=""
				shouldSavePayment={ false }
				upeMethods={ { card: 'woocommerce_payments' } }
				onLoadError={ jest.fn() }
			/>
		);

		expect( await onPaymentSetupCallback() ).toEqual( {
			type: 'error',
			message:
				'Invalid or missing payment details. Please ensure the provided payment method is correctly entered.',
		} );
		expect( mockCreatePaymentMethod ).not.toHaveBeenCalled();
	} );

	it( 'should return an error when the error message is passed as a prop', async () => {
		let onPaymentSetupCallback;

		act( () => {
			render(
				<PaymentProcessor
					activePaymentMethod="woocommerce_payments"
					api={ mockApi }
					paymentMethodId="card"
					emitResponse={ {} }
					errorMessage="Some generic error"
					eventRegistration={ {
						onPaymentSetup: ( callback ) =>
							( onPaymentSetupCallback = callback ),
					} }
					fingerprint=""
					shouldSavePayment={ false }
					upeMethods={ { card: 'woocommerce_payments' } }
				/>
			);
		} );

		expect( await onPaymentSetupCallback() ).toEqual( {
			type: 'error',
			message: 'Some generic error',
		} );
		expect( mockCreatePaymentMethod ).not.toHaveBeenCalled();
	} );

	it( 'should return success with the error data when createPaymentMethod fails', async () => {
		let onPaymentSetupCallback;
		mockCreatePaymentMethod = jest.fn().mockResolvedValue( {
			error: {
				code: 'code',
				decline_code: 'decline_code',
				message: 'Error creating payment method',
				type: 'card_error',
			},
		} );

		act( () => {
			render(
				<PaymentProcessor
					activePaymentMethod="woocommerce_payments"
					api={ mockApi }
					paymentMethodId="card"
					emitResponse={ {} }
					eventRegistration={ {
						onPaymentSetup: ( callback ) =>
							( onPaymentSetupCallback = callback ),
					} }
					fingerprint=""
					shouldSavePayment={ false }
					upeMethods={ { card: 'woocommerce_payments' } }
				/>
			);
		} );

		expect( mockCreatePaymentMethod ).not.toHaveBeenCalled();
		expect( await onPaymentSetupCallback() ).toEqual( {
			type: 'success',
			meta: {
				paymentMethodData: {
					payment_method: 'woocommerce_payments',
					'wcpay-payment-method': PAYMENT_METHOD_ERROR,
					'wcpay-payment-method-error-code': 'code',
					'wcpay-payment-method-error-decline-code': 'decline_code',
					'wcpay-payment-method-error-message':
						'Error creating payment method',
					'wcpay-payment-method-error-type': 'card_error',
					'wcpay-fraud-prevention-token': '',
					'wcpay-fingerprint': '',
				},
			},
		} );
		expect( mockCreatePaymentMethod ).toHaveBeenCalled();
	} );

	it( 'should return success when there are no failures', async () => {
		let onPaymentSetupCallback;
		mockCreatePaymentMethod = jest.fn().mockResolvedValue( {
			paymentMethod: {
				id: 'paymentMethodId',
			},
		} );

		act( () => {
			render(
				<PaymentProcessor
					activePaymentMethod="woocommerce_payments"
					api={ mockApi }
					paymentMethodId="card"
					emitResponse={ {} }
					eventRegistration={ {
						onPaymentSetup: ( callback ) =>
							( onPaymentSetupCallback = callback ),
					} }
					fingerprint=""
					shouldSavePayment={ false }
					upeMethods={ { card: 'woocommerce_payments' } }
				/>
			);
		} );

		expect( mockCreatePaymentMethod ).not.toHaveBeenCalled();
		expect( await onPaymentSetupCallback() ).toEqual( {
			type: 'success',
			meta: {
				paymentMethodData: {
					payment_method: 'woocommerce_payments',
					'wcpay-payment-method': 'paymentMethodId',
					'wcpay-fraud-prevention-token': '',
					'wcpay-fingerprint': '',
				},
			},
		} );
		expect( mockCreatePaymentMethod ).toHaveBeenCalled();
	} );
} );
