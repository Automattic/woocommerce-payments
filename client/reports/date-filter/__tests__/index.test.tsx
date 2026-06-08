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
import DateFilter from '../index';

describe( 'DateFilter', () => {
	it( 'renders the open popover with role="dialog" and an accessible name', async () => {
		render( <DateFilter value={ undefined } onChange={ jest.fn() } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: /^date$/i } )
		);

		const dialog = await screen.findByRole( 'dialog', {
			name: /date filter/i,
		} );
		expect( dialog ).toBeInTheDocument();
	} );

	it( 'uses onClear for chip clears without calling onChange', async () => {
		const onChange = jest.fn();
		const onClear = jest.fn();

		render(
			<DateFilter
				value={ {
					operator: 'between',
					value: [ '2026-04-01', '2026-04-30' ],
				} }
				onChange={ onChange }
				onClear={ onClear }
			/>
		);

		await userEvent.click(
			screen.getByRole( 'button', { name: /clear date filter/i } )
		);

		expect( onClear ).toHaveBeenCalledTimes( 1 );
		expect( onChange ).not.toHaveBeenCalled();
	} );
} );
