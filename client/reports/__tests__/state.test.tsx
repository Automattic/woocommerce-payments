/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { ReportsTabPanel } from '../tabs';

jest.mock( '../fees', () => ( {
	FeesReport: () => <div>Fees ledger table</div>,
} ) );

// The Fees tab owns its own loading/empty/error UI via `<FeesReport>` (see
// `client/reports/fees/index.tsx`). `ReportsTabPanel` short-circuits to that
// component for `tab === 'fees'` and never enters its own state branches for
// the Fees tab. The status-driven branches below therefore only matter for
// the Balance tab.
describe( 'Reports tab states', () => {
	it( 'renders the Balance empty state', () => {
		const { container } = render(
			<ReportsTabPanel
				tab="balance"
				status="empty"
				onReload={ jest.fn() }
			/>
		);

		expect( container.firstChild ).toHaveClass(
			'wcpay-reports-state--empty'
		);
		expect(
			screen.getByRole( 'heading', { level: 2 } )
		).toBeInTheDocument();
	} );

	it( 'renders a loading placeholder state', () => {
		render(
			<ReportsTabPanel
				tab="balance"
				status="loading"
				onReload={ jest.fn() }
			/>
		);

		expect( screen.getByRole( 'status' ) ).toContainElement(
			screen.getByRole( 'heading', { level: 2 } )
		);
	} );

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

	it( 'renders Balance error state with reload action', async () => {
		const onReload = jest.fn();
		render(
			<ReportsTabPanel
				tab="balance"
				status="error"
				onReload={ onReload }
			/>
		);

		const group = screen.getByRole( 'group' );

		expect(
			within( group ).getByRole( 'heading', { level: 2 } )
		).toBeInTheDocument();

		await userEvent.click(
			within( group ).getByRole( 'button', { name: /Reload/i } )
		);

		expect( onReload ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'moves focus to the Balance error heading after entering an error state', async () => {
		const onReload = jest.fn();
		const { rerender } = render(
			<ReportsTabPanel
				tab="balance"
				status="loading"
				onReload={ jest.fn() }
			/>
		);

		rerender(
			<ReportsTabPanel
				tab="balance"
				status="error"
				onReload={ onReload }
			/>
		);

		await waitFor( () => {
			expect(
				within( screen.getByRole( 'group' ) ).getByRole( 'heading', {
					level: 2,
				} )
			).toHaveFocus();
		} );
	} );

	it( 'moves focus to the persistent content heading after recovering from an error', async () => {
		const onReload = jest.fn();
		const { rerender } = render(
			<ReportsTabPanel
				tab="balance"
				status="error"
				onReload={ onReload }
			/>
		);
		const reloadButton = screen.getByRole( 'button', { name: /Reload/i } );

		await userEvent.click( reloadButton );
		expect( onReload ).toHaveBeenCalledTimes( 1 );
		expect( reloadButton ).toHaveFocus();

		rerender(
			<ReportsTabPanel
				tab="balance"
				status="empty"
				onReload={ jest.fn() }
			/>
		);

		await waitFor( () => {
			expect( screen.getByRole( 'heading', { level: 2 } ) ).toHaveFocus();
		} );
	} );
} );
