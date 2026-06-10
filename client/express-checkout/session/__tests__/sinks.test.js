/**
 * Internal dependencies
 */
import { createBlocksMetaSink, createClassicFormSink } from '../sinks';
import {
	appendPaymentMethodIdToForm,
	appendConfirmationTokenToForm,
	appendExpressPaymentTypeToForm,
	appendFraudPreventionTokenInputToForm,
} from 'wcpay/checkout/classic/upe-utils';
import { appendFingerprintInputToForm } from 'wcpay/checkout/utils/fingerprint';

jest.mock( 'wcpay/checkout/classic/upe-utils', () => ( {
	appendPaymentMethodIdToForm: jest.fn(),
	appendConfirmationTokenToForm: jest.fn(),
	appendExpressPaymentTypeToForm: jest.fn(),
	appendFraudPreventionTokenInputToForm: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/utils/fingerprint', () => ( {
	appendFingerprintInputToForm: jest.fn(),
} ) );

// eslint-disable-next-line @typescript-eslint/naming-convention
const responseTypes = { SUCCESS: 'success', ERROR: 'error', FAIL: 'fail' };

const confirmationTokenResult = {
	credentialId: 'ct_123',
	credentialType: 'confirmation_token',
	expressPaymentType: 'google_pay',
	stripePaymentMethodTypes: [ 'card' ],
};

const paymentMethodResult = {
	...confirmationTokenResult,
	credentialId: 'pm_123',
	credentialType: 'payment_method',
};

describe( 'createBlocksMetaSink', () => {
	beforeEach( () => {
		window.wcpayFraudPreventionToken = 'fraud-token';
	} );

	it( 'maps a confirmation token result to the WC Blocks payment data', () => {
		const sink = createBlocksMetaSink( {
			gatewayId: 'woocommerce_payments_google_pay',
			responseTypes,
		} );

		expect( sink.success( confirmationTokenResult ) ).toEqual( {
			type: 'success',
			meta: {
				paymentMethodData: {
					payment_method: 'woocommerce_payments_google_pay',
					'wcpay-confirmation-token': 'ct_123',
					express_payment_type: 'google_pay',
					'wcpay-express-payment-method-types': JSON.stringify( [
						'card',
					] ),
					'wcpay-fraud-prevention-token': 'fraud-token',
				},
			},
		} );
	} );

	it( 'uses the payment method key when not tokenizing', () => {
		const sink = createBlocksMetaSink( {
			gatewayId: 'woocommerce_payments_google_pay',
			responseTypes,
		} );

		expect(
			sink.success( paymentMethodResult ).meta.paymentMethodData
		).toEqual(
			expect.objectContaining( { 'wcpay-payment-method': 'pm_123' } )
		);
	} );

	it( 'returns a proper error response instead of a success sentinel', () => {
		const sink = createBlocksMetaSink( {
			gatewayId: 'woocommerce_payments_google_pay',
			responseTypes,
		} );

		expect( sink.error( 'declined' ) ).toEqual( {
			type: 'error',
			message: 'declined',
		} );
	} );
} );

describe( 'createClassicFormSink', () => {
	let onSubmit;
	let onError;
	let $form;

	beforeEach( () => {
		jest.clearAllMocks();
		onSubmit = jest.fn();
		onError = jest.fn();
		$form = { append: jest.fn() };
	} );

	it( 'appends a confirmation token, the express type, fraud signals, then submits', () => {
		const sink = createClassicFormSink( {
			$form,
			fingerprint: 'fp_123',
			onSubmit,
			onError,
		} );

		sink.success( confirmationTokenResult );

		expect( appendConfirmationTokenToForm ).toHaveBeenCalledWith(
			$form,
			'ct_123'
		);
		expect( appendPaymentMethodIdToForm ).not.toHaveBeenCalled();
		expect( appendExpressPaymentTypeToForm ).toHaveBeenCalledWith(
			$form,
			'google_pay'
		);
		expect( appendFingerprintInputToForm ).toHaveBeenCalledWith(
			$form,
			'fp_123'
		);
		expect( appendFraudPreventionTokenInputToForm ).toHaveBeenCalledWith(
			$form
		);
		expect( onSubmit ).toHaveBeenCalled();
	} );

	it( 'appends the payment method id when not tokenizing', () => {
		const sink = createClassicFormSink( {
			$form,
			fingerprint: 'fp_123',
			onSubmit,
			onError,
		} );

		sink.success( paymentMethodResult );

		expect( appendPaymentMethodIdToForm ).toHaveBeenCalledWith(
			$form,
			'pm_123'
		);
		expect( appendConfirmationTokenToForm ).not.toHaveBeenCalled();
	} );

	it( 'delegates errors to the injected handler without submitting', () => {
		const sink = createClassicFormSink( {
			$form,
			fingerprint: 'fp_123',
			onSubmit,
			onError,
		} );

		sink.error( 'declined' );

		expect( onError ).toHaveBeenCalledWith( 'declined' );
		expect( onSubmit ).not.toHaveBeenCalled();
	} );
} );
