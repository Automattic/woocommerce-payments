/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DateFilter from '../index';

describe( 'DateFilter', () => {
	it( 'renders the open popover with role="dialog" and an accessible name', async () => {
		render( <DateFilter value={ undefined } onChange={ jest.fn() } /> );

		fireEvent.click( screen.getByRole( 'button', { name: /^date$/i } ) );

		const dialog = await screen.findByRole( 'dialog', {
			name: /date filter/i,
		} );
		expect( dialog ).toBeInTheDocument();
	} );
} );
