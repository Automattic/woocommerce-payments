/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import GeneralSettings from '..';
import {
	useDevMode,
	useIsWCPayEnabled,
	useTestMode,
	useTitle,
	useDescription,
} from 'data';

jest.mock( 'data', () => ( {
	useDevMode: jest.fn(),
	useIsWCPayEnabled: jest.fn(),
	useTestMode: jest.fn(),
	useTitle: jest.fn(),
	useDescription: jest.fn(),
} ) );

describe( 'GeneralSettings', () => {
	beforeEach( () => {
		useDevMode.mockReturnValue( false );
		useIsWCPayEnabled.mockReturnValue( [ false, jest.fn() ] );
		useTestMode.mockReturnValue( [ false, jest.fn() ] );
		useTitle.mockReturnValue( [ '', jest.fn() ] );
		useDescription.mockReturnValue( [ '', jest.fn() ] );
	} );

	it( 'renders', () => {
		render( <GeneralSettings /> );

		expect(
			screen.queryByText( 'Enable WooCommerce Payments' )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Title' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Description' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Enable test mode' ) ).toBeInTheDocument();
	} );

	it.each( [ [ true ], [ false ] ] )(
		'displays WCPay enabled = %s state from data store',
		( isEnabled ) => {
			useIsWCPayEnabled.mockReturnValue( [ isEnabled ] );

			render( <GeneralSettings /> );

			const enableWCPayCheckbox = screen.getByLabelText(
				'Enable WooCommerce Payments'
			);

			let expectation = expect( enableWCPayCheckbox );
			if ( ! isEnabled ) {
				expectation = expectation.not;
			}
			expectation.toBeChecked();
		}
	);

	it.each( [ [ true ], [ false ] ] )(
		'updates WCPay enabled state to %s when toggling checkbox',
		( isEnabled ) => {
			const updateIsWCPayEnabledMock = jest.fn();
			useIsWCPayEnabled.mockReturnValue( [
				isEnabled,
				updateIsWCPayEnabledMock,
			] );

			render( <GeneralSettings /> );

			const enableWCPayCheckbox = screen.getByLabelText(
				'Enable WooCommerce Payments'
			);

			fireEvent.click( enableWCPayCheckbox );
			expect( updateIsWCPayEnabledMock ).toHaveBeenCalledWith(
				! isEnabled
			);
		}
	);

	it( 'calls updateTitle() when the title field value changes', () => {
		const updateTitle = jest.fn();
		useTitle.mockReturnValue( [ 'Credit card', updateTitle ] );

		render( <GeneralSettings /> );

		fireEvent.change( screen.getByLabelText( 'Title' ), {
			target: { value: 'Credit card - update' },
		} );

		expect( updateTitle ).toHaveBeenCalledWith( 'Credit card - update' );
	} );

	it( 'calls updateDescription() when the description field value changes', () => {
		const updateDescription = jest.fn();
		useDescription.mockReturnValue( [
			'Enter your card details',
			updateDescription,
		] );

		render( <GeneralSettings /> );

		fireEvent.change( screen.getByLabelText( 'Description' ), {
			target: { value: 'Enter your card details - update' },
		} );

		expect( updateDescription ).toHaveBeenCalledWith(
			'Enter your card details - update'
		);
	} );
} );
