/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { ReportsTabPanel } from '../tabs';

describe( 'Reports tab states', () => {
	it( 'renders the Balance empty state copy', () => {
		render(
			<ReportsTabPanel
				tab="balance"
				status="empty"
				onReload={ jest.fn() }
			/>
		);

		expect(
			screen.getByRole( 'heading', { name: 'No balance activity' } )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				"Your Balance summary will appear here once there's enough data to display."
			)
		).toBeInTheDocument();
	} );

	it( 'renders the Fees empty state copy', () => {
		render(
			<ReportsTabPanel tab="fees" status="empty" onReload={ jest.fn() } />
		);

		expect(
			screen.getByRole( 'heading', { name: 'No fees yet' } )
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
			screen.getByRole( 'heading', { name: 'Balance unavailable' } )
		).toBeInTheDocument();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);

		expect( onReload ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'renders Fees error copy and reload action', async () => {
		const onReload = jest.fn();
		render(
			<ReportsTabPanel tab="fees" status="error" onReload={ onReload } />
		);

		expect(
			screen.getByRole( 'heading', { name: 'Fees report unavailable' } )
		).toBeInTheDocument();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);

		expect( onReload ).toHaveBeenCalledTimes( 1 );
	} );
} );
