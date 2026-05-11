/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { ReportsTabPanel } from '../tabs';

describe( 'Reports tab states', () => {
	it( 'renders the Balance empty state', () => {
		render(
			<ReportsTabPanel
				tab="balance"
				status="empty"
				onReload={ jest.fn() }
			/>
		);

		expect(
			screen.getByRole( 'heading', { name: /No balance/i } )
		).toBeInTheDocument();
		expect( screen.getByText( /Balance summary/i ) ).toBeInTheDocument();
	} );

	it( 'renders the Fees empty state', () => {
		render(
			<ReportsTabPanel tab="fees" status="empty" onReload={ jest.fn() } />
		);

		expect(
			screen.getByRole( 'heading', { name: /No fees/i } )
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

		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			/Loading report/i
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

		expect(
			screen.getByRole( 'heading', { name: /partially loaded/i } )
		).toBeInTheDocument();
	} );

	it( 'renders Balance error copy and reload action', async () => {
		const onReload = jest.fn();
		render(
			<ReportsTabPanel
				tab="balance"
				status="error"
				onReload={ onReload }
			/>
		);

		expect(
			screen.getByRole( 'heading', { name: /Balance unavailable/i } )
		).toBeInTheDocument();

		await userEvent.click(
			screen.getByRole( 'button', { name: /Reload/i } )
		);

		expect( onReload ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'renders Fees error copy and reload action', async () => {
		const onReload = jest.fn();
		render(
			<ReportsTabPanel tab="fees" status="error" onReload={ onReload } />
		);

		expect(
			screen.getByRole( 'heading', { name: /Fees report unavailable/i } )
		).toBeInTheDocument();

		await userEvent.click(
			screen.getByRole( 'button', { name: /Reload/i } )
		);

		expect( onReload ).toHaveBeenCalledTimes( 1 );
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
			expect(
				screen.getByRole( 'heading', { name: /No balance/i } )
			).toHaveFocus();
		} );
	} );
} );
