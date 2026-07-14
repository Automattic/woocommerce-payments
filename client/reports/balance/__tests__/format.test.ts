/** @format */

/**
 * Internal dependencies
 */
import { formatBalanceAmount } from '../format';

// The Balance report's other suites mock `multi-currency/interface/functions`
// to keep their assertions readable. This suite deliberately does not: it runs
// `formatBalanceAmount` through the real currency utility so the rendered
// strings are pinned to the Balance report design rather than to a stand-in.

declare const global: {
	wcpaySettings?: typeof wcpaySettings;
};

describe( 'formatBalanceAmount', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			...( global.wcpaySettings ?? {} ),
			shouldUseExplicitPrice: true,
			zeroDecimalCurrencies: [ 'vnd', 'jpy', 'xpf' ],
			connect: { country: 'US' },
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
		} as unknown as typeof wcpaySettings;
	} );

	// Every row of the Balance report design, in report order.
	it.each( [
		[ 'Starting balance', -123400, '-$1,234.00 USD' ],
		[ 'Total charges captured', 34514376, '+$345,143.76 USD' ],
		[ 'Fees', -1783357, '-$17,833.57 USD' ],
		[ 'Charge fees', -1723025, '-$17,230.25 USD' ],
		[ 'Deposit fees', -52332, '-$523.32 USD' ],
		[ 'Disputes fees', -8000, '-$80.00 USD' ],
		[ 'Refunds', -3203311, '-$32,033.11 USD' ],
		[ 'Disputes', -87598, '-$875.98 USD' ],
		[ 'Net balance change', 29340110, '+$293,401.10 USD' ],
		[ 'Payouts', -28267288, '-$282,672.88 USD' ],
		[ 'Ending balance', -1072822, '-$10,728.22 USD' ],
	] )( 'renders the %s row as %s', ( label, amount, expected ) => {
		expect( formatBalanceAmount( amount as number, 'usd' ) ).toBe(
			expected
		);
	} );

	it( 'renders zero without a sign, so an empty line implies no direction', () => {
		expect( formatBalanceAmount( 0, 'usd' ) ).toBe( '$0.00 USD' );
	} );

	it( 'does not add a decimal part to zero-decimal currencies', () => {
		expect( formatBalanceAmount( 3450, 'jpy' ) ).toBe( '+¥3,450 JPY' );
	} );

	it( 'omits the ISO code when the store has explicit pricing turned off', () => {
		global.wcpaySettings = {
			...global.wcpaySettings,
			shouldUseExplicitPrice: false,
		} as unknown as typeof wcpaySettings;

		expect( formatBalanceAmount( 123400, 'usd' ) ).toBe( '+$1,234.00' );
	} );
} );
