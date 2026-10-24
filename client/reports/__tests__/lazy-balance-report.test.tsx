/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

jest.mock( '../balance', () => ( {
	BalanceReport: ( { onReload }: { onReload: () => void } ) => (
		<button type="button" onClick={ onReload }>
			Balance report loaded
		</button>
	),
} ) );

/**
 * Internal dependencies
 */
import { LazyLoadedBalanceReport } from '../lazy-balance-report';

describe( 'LazyLoadedBalanceReport', () => {
	it( 'renders the loading fallback and then the lazy Balance report', async () => {
		const onReload = jest.fn();

		render( <LazyLoadedBalanceReport onReload={ onReload } /> );

		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading report'
		);

		await userEvent.click(
			await screen.findByRole( 'button', {
				name: 'Balance report loaded',
			} )
		);

		expect( onReload ).toHaveBeenCalledTimes( 1 );
	} );
} );
