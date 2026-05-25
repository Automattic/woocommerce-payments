/** @format */

import React from 'react';
import { render, screen, within } from '@testing-library/react';

jest.mock( 'multi-currency/interface/functions', () => ( {
	formatExplicitCurrency: (
		amount: number,
		currency: string,
		skipSymbol?: boolean
	) =>
		skipSymbol ? `${ amount } ${ currency }` : `${ currency } ${ amount }`,
} ) );

jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromString: ( value: string ) =>
		`formatted ${ value.slice( 0, 10 ) }`,
} ) );

import balanceSummaryFixture from 'wcpay/data/reports/fixtures/balance-summary';
import { BalanceSummaryTable } from '../summary-table';
import { getVisibleBalanceRows } from '../rows';

describe( 'BalanceSummaryTable', () => {
	it( 'renders one row per visible balance row with label and amount', () => {
		const visibleRows = getVisibleBalanceRows( balanceSummaryFixture );

		render(
			<BalanceSummaryTable
				visibleRows={ visibleRows }
				summary={ balanceSummaryFixture }
				displayPeriod={ {
					start: '2024-03-01T00:00:00',
					end: '2024-03-31T23:59:59',
				} }
				currency="usd"
			/>
		);

		const table = screen.getByRole( 'table' );
		const rows = within( table ).getAllByRole( 'row' );

		expect( rows.length ).toBeGreaterThanOrEqual( visibleRows.length );
		expect(
			within( table ).getByText( /Starting balance/ )
		).toBeInTheDocument();
	} );

	it( 'accepts optional className and ariaHidden props for skeleton use', () => {
		const visibleRows = getVisibleBalanceRows( balanceSummaryFixture );

		const { container } = render(
			<BalanceSummaryTable
				visibleRows={ visibleRows }
				summary={ balanceSummaryFixture }
				displayPeriod={ {
					start: '2024-03-01T00:00:00',
					end: '2024-03-31T23:59:59',
				} }
				currency="usd"
				className="custom-class"
				ariaHidden
			/>
		);

		const card = container.querySelector( '.wcpay-reports-balance__card' );
		expect( card ).toHaveClass( 'custom-class' );
		expect( card ).toHaveAttribute( 'aria-hidden', 'true' );
	} );
} );
