/**
 * External dependencies
 */
import { renderHook } from '@testing-library/react-hooks';

/**
 * Internal dependencies
 */
import { useTransactionAmounts } from '../hooks';
import { formatCurrency } from 'multi-currency/interface/functions';

jest.mock( 'multi-currency/interface/functions', () => ( {
	formatCurrency: jest.fn(),
} ) );

describe( 'useTransactionAmounts', () => {
	beforeEach( () => {
		( formatCurrency as jest.Mock ).mockReset();
	} );

	it( 'returns null when capture event is undefined', () => {
		const { result } = renderHook( () =>
			useTransactionAmounts( undefined )
		);
		expect( result.current ).toBeNull();
	} );

	it( 'returns null when transaction details are missing', () => {
		const { result } = renderHook( () =>
			useTransactionAmounts( {
				type: 'captured',
			} as any )
		);
		expect( result.current ).toBeNull();
	} );

	it( 'formats amounts for same currency transaction', () => {
		( formatCurrency as jest.Mock ).mockReturnValue( '$100.00' );

		const captureEvent = {
			type: 'captured',
			transaction_details: {
				store_amount: 10000,
				store_currency: 'USD',
				customer_amount: 10000,
				customer_currency: 'USD',
			},
		};

		const { result } = renderHook( () =>
			useTransactionAmounts( captureEvent as any )
		);

		expect( result.current ).toEqual( {
			formattedStoreAmount: '$100.00 USD',
			formattedCustomerAmount: '$100.00 USD',
			isMultiCurrency: false,
			formattedAmount: '$100.00 USD',
		} );
	} );

	it( 'formats amounts for multi-currency transaction', () => {
		( formatCurrency as jest.Mock )
			.mockReturnValueOnce( '€85.00' ) // store amount
			.mockReturnValueOnce( '$100.00' ); // customer amount

		const captureEvent = {
			type: 'captured',
			transaction_details: {
				store_amount: 8500,
				store_currency: 'EUR',
				customer_amount: 10000,
				customer_currency: 'USD',
			},
		};

		const { result } = renderHook( () =>
			useTransactionAmounts( captureEvent as any )
		);

		expect( result.current ).toEqual( {
			formattedStoreAmount: '€85.00 EUR',
			formattedCustomerAmount: '$100.00 USD',
			isMultiCurrency: true,
			formattedAmount: '$100.00 USD → €85.00 EUR',
		} );
	} );
} );
