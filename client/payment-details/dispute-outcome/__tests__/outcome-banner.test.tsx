/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, within } from '@testing-library/react';

/**
 * Internal dependencies
 */
import OutcomeBanner from '../outcome-banner';
import {
	wonFixture,
	lostFixture,
	warningClosedFixture,
} from '../__fixtures__/outcome-banner';
import type { Charge } from 'wcpay/types/charges';

// Force the WP date formatter to UTC so day boundaries don't shift based on
// the test runner's local timezone. Matches the pattern used in
// `client/utils/__tests__/date-time.test.ts`.
jest.mock( '@wordpress/date', () => ( {
	dateI18n: jest.fn( ( format, date, timezone ) =>
		jest
			.requireActual( '@wordpress/date' )
			.dateI18n( format, date, timezone || 'UTC' )
	),
} ) );

const originalWcpaySettings = window.wcpaySettings;

describe( 'OutcomeBanner', () => {
	beforeAll( () => {
		// Pin a deterministic date format and the multi-currency formatter's
		// required settings: zero-decimal currencies list, store country,
		// and the USD currencyData entry the fixtures exercise.
		window.wcpaySettings = {
			...originalWcpaySettings,
			dateFormat: 'Y-m-d',
			timeFormat: 'H:i',
			zeroDecimalCurrencies: [ 'jpy', 'krw', 'vnd' ],
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

	afterAll( () => {
		window.wcpaySettings = originalWcpaySettings;
	} );

	describe( 'status variants', () => {
		it( 'renders "Disputed: Won" for won outcomes', () => {
			render( <OutcomeBanner { ...wonFixture } /> );
			expect(
				screen.getByText( /Disputed:\s*Won/i )
			).toBeInTheDocument();
		} );

		it( 'renders "Disputed: Lost" for lost outcomes', () => {
			render( <OutcomeBanner { ...lostFixture } /> );
			expect(
				screen.getByText( /Disputed:\s*Lost/i )
			).toBeInTheDocument();
		} );

		it( 'renders the inquiry-closed label for warning_closed outcomes', () => {
			// Inquiries are not prefixed with "Disputed:" (the chip suppresses
			// the prefix for any status that starts with `warning_`). The
			// canonical label from the existing chip mapping is "Inquiry:
			// Closed". This component reuses the chip rather than overriding
			// the mapping.
			render( <OutcomeBanner { ...warningClosedFixture } /> );
			expect(
				screen.getByText( /Inquiry:\s*Closed/i )
			).toBeInTheDocument();
			// Defense-in-depth: ensure the "Disputed:" prefix didn't sneak in.
			expect(
				screen.queryByText( /Disputed:/i )
			).not.toBeInTheDocument();
		} );
	} );

	describe( 'issuer name', () => {
		it( 'renders the card issuer from the charge', () => {
			render( <OutcomeBanner { ...lostFixture } /> );
			expect( screen.getByText( 'Wells Fargo' ) ).toBeInTheDocument();
		} );

		it( 'renders the BNPL provider name via getBankName fallback', () => {
			// warningClosedFixture uses a klarna payment method, exercising
			// the BNPL branch of getBankName which returns "Klarna" rather
			// than a card issuer.
			render( <OutcomeBanner { ...warningClosedFixture } /> );
			expect( screen.getByText( 'Klarna' ) ).toBeInTheDocument();
		} );

		it( 'falls back to "Unknown bank" when no issuer is resolvable', () => {
			const unknownIssuerCharge = {
				...lostFixture.charge,
				payment_method_details: {
					type: 'card',
					card: {}, // no `issuer` field
				},
			} as Charge;
			render(
				<OutcomeBanner
					dispute={ lostFixture.dispute }
					charge={ unknownIssuerCharge }
				/>
			);
			expect( screen.getByText( 'Unknown bank' ) ).toBeInTheDocument();
		} );
	} );

	describe( 'amounts row', () => {
		it( 'renders Deducted / Fees / Net from the lost-case deduction transaction', () => {
			render( <OutcomeBanner { ...lostFixture } /> );

			const items = screen.getAllByRole( 'term' );
			const labels = items.map( ( el ) => el.textContent );
			expect( labels ).toEqual( [ 'Deducted', 'Fees', 'Net' ] );

			// Deduction in the fixture: amount: -5000, fee: 1500 (cents).
			// renderAmount uses absolute values + explicit currency.
			expect( screen.getByText( /\$50\.00/ ) ).toBeInTheDocument();
			expect( screen.getByText( /\$15\.00/ ) ).toBeInTheDocument();
			expect( screen.getByText( /\$65\.00/ ) ).toBeInTheDocument();
		} );

		it( 'renders the won-case amounts from the reversal transaction', () => {
			render( <OutcomeBanner { ...wonFixture } /> );
			// Reversal in the fixture: amount: 5000, fee: -1500.
			// Display uses magnitudes, so figures match the lost case.
			expect( screen.getByText( /\$50\.00/ ) ).toBeInTheDocument();
			expect( screen.getByText( /\$15\.00/ ) ).toBeInTheDocument();
			expect( screen.getByText( /\$65\.00/ ) ).toBeInTheDocument();
		} );

		it( 'renders placeholders when no relevant balance transaction exists (warning_closed)', () => {
			render( <OutcomeBanner { ...warningClosedFixture } /> );
			const banner = screen.getByTestId( 'dispute-outcome-banner' );
			const definitions = within( banner ).getAllByRole( 'definition' );
			// All three amount cells should render the em-dash placeholder.
			expect( definitions ).toHaveLength( 3 );
			definitions.forEach( ( dd ) => {
				expect( dd ).toHaveTextContent( '—' );
			} );
		} );

		it( 'renders placeholders when the reversal carries null amounts (RSM-1168 server bug)', () => {
			// Real production payload for the won case ships the reversal row
			// with `amount: null` and `fee: null`. The component should
			// degrade to placeholders rather than rendering "$NaN".
			const brokenWonReversal = {
				dispute: {
					...wonFixture.dispute,
					balance_transactions: [
						{
							...wonFixture.dispute.balance_transactions[ 0 ],
						},
						{
							currency: 'usd',
							// eslint-disable-next-line @typescript-eslint/no-explicit-any
							amount: null as any,
							// eslint-disable-next-line @typescript-eslint/no-explicit-any
							fee: null as any,
							reporting_category: 'dispute_reversal' as const,
							created: 1715040000,
						},
					],
				},
				charge: wonFixture.charge,
			};
			render( <OutcomeBanner { ...brokenWonReversal } /> );
			const banner = screen.getByTestId( 'dispute-outcome-banner' );
			const definitions = within( banner ).getAllByRole( 'definition' );
			expect( definitions ).toHaveLength( 3 );
			definitions.forEach( ( dd ) => {
				expect( dd ).toHaveTextContent( '—' );
			} );
		} );
	} );

	describe( 'decision date', () => {
		it( 'uses the reversal timestamp on a won outcome', () => {
			render( <OutcomeBanner { ...wonFixture } /> );
			// Reversal `created` = 1715040000 → 2024-05-07 UTC.
			expect( screen.getByText( /Decision date:/ ) ).toHaveTextContent(
				'2024-05-07'
			);
		} );

		it( 'uses the deduction timestamp on a lost outcome', () => {
			render( <OutcomeBanner { ...lostFixture } /> );
			// Deduction `created` = 1714780800 → 2024-05-04 UTC.
			expect( screen.getByText( /Decision date:/ ) ).toHaveTextContent(
				'2024-05-04'
			);
		} );

		it( 'falls back to dispute.created when balance transactions are absent', () => {
			render( <OutcomeBanner { ...warningClosedFixture } /> );
			// dispute.created = 1714003200 → 2024-04-25 UTC.
			expect( screen.getByText( /Decision date:/ ) ).toHaveTextContent(
				'2024-04-25'
			);
		} );
	} );
} );
