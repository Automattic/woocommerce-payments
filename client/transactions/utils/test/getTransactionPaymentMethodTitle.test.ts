/**
 * Internal dependencies
 */
import { getTransactionPaymentMethodTitle } from '../getTransactionPaymentMethodTitle';
import { TRANSACTION_PAYMENT_METHOD_TITLES } from 'wcpay/constants/payment-method';

describe( 'getTransactionPaymentMethodTitle', () => {
	const mockConfig = {
		custom_method: {
			title: 'Custom Payment Method',
		},
		visa: {
			title: 'Custom Visa Title',
		},
	};

	beforeEach( () => {
		// Clear any global config before each test
		delete window.wooPaymentsPaymentMethodsConfig;
	} );

	it( 'should return title from provided config when available', () => {
		expect(
			getTransactionPaymentMethodTitle( 'custom_method', mockConfig )
		).toBe( 'Custom Payment Method' );
	} );

	it( 'should return title from global config when available', () => {
		window.wooPaymentsPaymentMethodsConfig = mockConfig;
		expect( getTransactionPaymentMethodTitle( 'custom_method' ) ).toBe(
			'Custom Payment Method'
		);
	} );

	it( 'should fallback to TRANSACTION_PAYMENT_METHOD_TITLES when method not in config', () => {
		expect( getTransactionPaymentMethodTitle( 'visa', {} ) ).toBe(
			TRANSACTION_PAYMENT_METHOD_TITLES.visa
		);
	} );

	it( 'should prefer config title over TRANSACTION_PAYMENT_METHOD_TITLES', () => {
		expect( getTransactionPaymentMethodTitle( 'visa', mockConfig ) ).toBe(
			'Custom Visa Title'
		);
	} );

	it( 'should fallback to payment method ID when no title is found', () => {
		expect( getTransactionPaymentMethodTitle( 'unknown_method', {} ) ).toBe(
			'unknown_method'
		);
	} );

	it( 'should handle undefined config gracefully', () => {
		expect( getTransactionPaymentMethodTitle( 'visa', undefined ) ).toBe(
			TRANSACTION_PAYMENT_METHOD_TITLES.visa
		);
	} );
} );
