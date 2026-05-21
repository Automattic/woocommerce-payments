/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { ReportsTabPanel } from '../tabs';

jest.mock( '../fees', () => ( {
	FeesReport: () => <div>Fees ledger table</div>,
} ) );

jest.mock( '../balance', () => ( {
	BalanceReport: () => <div>Balance summary table</div>,
} ) );

describe( 'Reports tab states', () => {
	it( 'renders an accessible loading status while the Fees report chunk loads', async () => {
		render(
			<ReportsTabPanel tab="fees" status="ready" onReload={ jest.fn() } />
		);

		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading report'
		);
		expect(
			await screen.findByText( 'Fees ledger table' )
		).toBeInTheDocument();
	} );

	it( 'renders an accessible loading status while the Balance report chunk loads', async () => {
		render(
			<ReportsTabPanel
				tab="balance"
				status="ready"
				onReload={ jest.fn() }
			/>
		);

		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading report'
		);
		expect(
			await screen.findByText( 'Balance summary table' )
		).toBeInTheDocument();
	} );

	it( 'renders the Balance report when the Balance tab is selected regardless of status', async () => {
		render(
			<ReportsTabPanel
				tab="balance"
				status="error"
				onReload={ jest.fn() }
			/>
		);

		expect(
			await screen.findByText( 'Balance summary table' )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'group' ) ).not.toBeInTheDocument();
	} );

	it( 'renders the Fees report when the Fees tab is selected (regardless of status)', async () => {
		render(
			<ReportsTabPanel tab="fees" status="ready" onReload={ jest.fn() } />
		);

		expect(
			await screen.findByText( 'Fees ledger table' )
		).toBeInTheDocument();
	} );

	it( 'still routes to FeesReport when an error status is passed for the Fees tab', async () => {
		// FeesReport surfaces its own error UI internally; the outer panel
		// must not override or duplicate it.
		render(
			<ReportsTabPanel tab="fees" status="error" onReload={ jest.fn() } />
		);

		expect(
			await screen.findByText( 'Fees ledger table' )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'group' ) ).not.toBeInTheDocument();
	} );
} );
