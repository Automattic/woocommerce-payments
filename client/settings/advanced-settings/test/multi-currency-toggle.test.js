/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { useMultiCurrency } from 'wcpay/data';
import MultiCurrencyToggle from '../multi-currency-toggle';

jest.mock( '../../../data', () => ( {
	useMultiCurrency: jest.fn().mockReturnValue( [ true, jest.fn() ] ),
} ) );

describe( 'MultiCurrencyToggle', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders the component', () => {
		render( <MultiCurrencyToggle /> );

		expect(
			screen.queryByLabelText( 'Enable Multi-Currency' )
		).toBeInTheDocument();
	} );

	it.each( [ [ true ], [ false ] ] )(
		'updates Multi-Currency enabled state to %s when toggling checkbox',
		( isEnabled ) => {
			const updateIsMultiCurrencyEnabledMock = jest.fn();
			useMultiCurrency.mockReturnValue( [
				isEnabled,
				updateIsMultiCurrencyEnabledMock,
			] );

			render( <MultiCurrencyToggle /> );

			const enableMultiCurrencyCheckbox = screen.getByLabelText(
				'Enable Multi-Currency'
			);

			userEvent.click( enableMultiCurrencyCheckbox );
			expect( updateIsMultiCurrencyEnabledMock ).toHaveBeenCalledWith(
				! isEnabled
			);
		}
	);
} );
