/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import DisputeDefenderToggle from '../dispute-defender-toggle';
import { useDisputeDefender } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useDisputeDefender: jest.fn(),
} ) );

describe( 'DisputeDefenderToggle', () => {
	beforeEach( () => {
		useDisputeDefender.mockReturnValue( [ false, jest.fn() ] );
	} );

	it( 'renders the checkbox with the correct label', () => {
		render( <DisputeDefenderToggle /> );
		expect(
			screen.getByRole( 'checkbox', {
				name: /enable dispute defender ai/i,
			} )
		).toBeInTheDocument();
	} );

	it( 'reflects the current enabled state', () => {
		useDisputeDefender.mockReturnValue( [ true, jest.fn() ] );
		render( <DisputeDefenderToggle /> );
		expect( screen.getByTestId( 'dispute-defender-toggle' ) ).toBeChecked();
	} );

	it( 'calls update when toggled', async () => {
		const update = jest.fn();
		useDisputeDefender.mockReturnValue( [ false, update ] );
		render( <DisputeDefenderToggle /> );
		await userEvent.click(
			screen.getByTestId( 'dispute-defender-toggle' )
		);
		expect( update ).toHaveBeenCalledWith( true );
	} );
} );
