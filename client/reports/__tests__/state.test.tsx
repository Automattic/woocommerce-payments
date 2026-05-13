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

	it( 'renders the Fees empty state', () => {
		const { container } = render(
			<ReportsTabPanel tab="fees" status="empty" onReload={ jest.fn() } />
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

	it( 'renders a partial placeholder state', () => {
		render(
			<ReportsTabPanel
				tab="balance"
				status="partial"
				onReload={ jest.fn() }
			/>
		);

		expect( screen.getByRole( 'status' ) ).toContainElement(
			screen.getByRole( 'heading', { level: 2 } )
		);
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

	it( 'renders Fees error state with reload action', async () => {
		const onReload = jest.fn();
		render(
			<ReportsTabPanel tab="fees" status="error" onReload={ onReload } />
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

	it( 'moves focus to the error heading after entering an error state', async () => {
		const onReload = jest.fn();
		const { rerender } = render(
			<ReportsTabPanel
				tab="balance"
				status="loading"
				onReload={ jest.fn() }
			/>
		);

		rerender(
			<ReportsTabPanel tab="fees" status="error" onReload={ onReload } />
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
