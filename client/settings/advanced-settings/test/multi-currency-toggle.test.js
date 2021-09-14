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

	it( 'sets the heading as focused after rendering', () => {
		render( <MultiCurrencyToggle /> );

		expect(
			screen.getByText( 'Enable Customer Multi Currency' )
		).toHaveFocus();
	} );

	it.each( [ [ true ], [ false ] ] )(
		'updates multi currency enabled state to %s when toggling checkbox',
		( isEnabled ) => {
			const updateIsMultiCurrencyEnabledMock = jest.fn();
			useMultiCurrency.mockReturnValue( [
				isEnabled,
				updateIsMultiCurrencyEnabledMock,
			] );

			render( <MultiCurrencyToggle /> );

			const enableMultiCurrencyCheckbox = screen.getByLabelText(
				'Allow your customers to shop and pay in their local currency.'
			);

			userEvent.click( enableMultiCurrencyCheckbox );
			expect( updateIsMultiCurrencyEnabledMock ).toHaveBeenCalledWith(
				! isEnabled
			);
		}
	);
} );
