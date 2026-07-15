/** @format */

/**
 * Internal dependencies
 */
import { formatBalanceAmount, getBalanceCSV } from '../format';
import type { BalanceRow } from '../rows';

// The Balance report's other suites mock `multi-currency/interface/functions`
// to keep their assertions readable. This suite deliberately does not: it runs
// the formatting helpers through the real currency utility so the rendered
// strings are pinned to the Balance report design rather than to a stand-in.

declare const global: {
	wcpaySettings?: typeof wcpaySettings;
};

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

describe( 'formatBalanceAmount', () => {
	// Every row of the Balance report design, in report order.
	it.each( [
		{
			label: 'Starting balance',
			amount: -123400,
			expected: '-$1,234.00 USD',
		},
		{
			label: 'Total charges captured',
			amount: 34514376,
			expected: '+$345,143.76 USD',
		},
		{ label: 'Fees', amount: -1783357, expected: '-$17,833.57 USD' },
		{ label: 'Charge fees', amount: -1723025, expected: '-$17,230.25 USD' },
		{ label: 'Deposit fees', amount: -52332, expected: '-$523.32 USD' },
		{ label: 'Disputes fees', amount: -8000, expected: '-$80.00 USD' },
		{ label: 'Refunds', amount: -3203311, expected: '-$32,033.11 USD' },
		{ label: 'Disputes', amount: -87598, expected: '-$875.98 USD' },
		{
			label: 'Net balance change',
			amount: 29340110,
			expected: '+$293,401.10 USD',
		},
		{ label: 'Payouts', amount: -28267288, expected: '-$282,672.88 USD' },
		{
			label: 'Ending balance',
			amount: -1072822,
			expected: '-$10,728.22 USD',
		},
	] )( 'renders the $label row as $expected', ( { amount, expected } ) => {
		expect( formatBalanceAmount( amount, 'usd' ) ).toBe( expected );
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

// The other Balance suites stub `formatExportAmount` with `amount => amount / 100`,
// which ignores its currency argument. That hides the branch that decides whether
// the `/100` happens at all, so a zero-decimal currency could silently be divided
// and no test would catch it. This suite runs the real helper to pin that branch.
describe( 'getBalanceCSV', () => {
	const displayPeriod = {
		start: '2026-05-01T00:00:00.000Z',
		end: '2026-05-14T23:59:59.999Z',
	};
	const row: BalanceRow = {
		key: 'total_charges_captured',
		label: 'Total charges captured',
		getAmount: () => 162672,
	};

	const buildCSV = ( currency: string ) =>
		getBalanceCSV( {
			visibleRows: [ row ],
			summary: {} as Parameters< BalanceRow[ 'getAmount' ] >[ 0 ],
			displayPeriod,
			currency,
		} );

	it( 'exports a two-decimal currency in major units', () => {
		expect( buildCSV( 'usd' ) ).toContain(
			',"Total charges captured",1626.72,'
		);
	} );

	it( 'leaves a zero-decimal currency amount unscaled', () => {
		expect( buildCSV( 'jpy' ) ).toContain(
			',"Total charges captured",162672,'
		);
	} );

	it( 'flips the sign of displayNegative outflow rows to match the screen', () => {
		const payouts: BalanceRow = {
			key: 'payouts',
			label: 'Payouts',
			displayNegative: true,
			getAmount: () => 1102608,
		};

		const csv = getBalanceCSV( {
			visibleRows: [ payouts ],
			summary: {} as Parameters< BalanceRow[ 'getAmount' ] >[ 0 ],
			displayPeriod,
			currency: 'usd',
		} );

		expect( csv ).toContain( ',Payouts,-11026.08,' );
	} );
} );
