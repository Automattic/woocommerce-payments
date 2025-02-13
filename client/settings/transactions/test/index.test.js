/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Transactions from '..';
import {
	useGetSavingError,
	useAccountBusinessSupportEmail,
	useAccountBusinessSupportPhone,
	useManualCapture,
	useSavedCards,
	useCardPresentEligible,
} from '../../../data';
import { select } from '@wordpress/data';

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
} ) );
const settingsMock = {
	account_country: 'US',
};

select.mockReturnValue( {
	getSettings: () => settingsMock,
} );

jest.mock( 'wcpay/data', () => ( {
	useAccountBusinessSupportEmail: jest.fn(),
	useAccountBusinessSupportPhone: jest.fn(),
	useManualCapture: jest.fn(),
	useGetSavingError: jest.fn(),
	useSavedCards: jest.fn(),
	useDevMode: jest.fn(),
	useTestModeOnboarding: jest.fn(),
	useCardPresentEligible: jest.fn(),
} ) );

describe( 'Settings - Transactions', () => {
	beforeEach( () => {
		useAccountBusinessSupportEmail.mockReturnValue( [
			'test@test.com',
			jest.fn(),
		] );
		useAccountBusinessSupportPhone.mockReturnValue( [
			'+12345678901',
			jest.fn(),
		] );
		useManualCapture.mockReturnValue( [ false, jest.fn() ] );
		useGetSavingError.mockReturnValue( null );
		useSavedCards.mockReturnValue( [ false, jest.fn() ] );
		useCardPresentEligible.mockReturnValue( [ false ] );
		window.wcpaySettings = {
			accountStatus: {
				country: 'US',
			},
		};
	} );

	it( 'display ipp payment notice', async () => {
		useCardPresentEligible.mockReturnValue( [ true ] );

		render( <Transactions /> );

		expect(
			screen.getByRole( 'link', { name: /In-Person Payments/i } )
		).toBeInTheDocument();

		expect(
			screen.getByText( new RegExp( 'The setting is not applied to' ) )
		).toBeInTheDocument();
	} );

	it( "shouldn't display ipp payment notice when it is not eligible for card present", async () => {
		useCardPresentEligible.mockReturnValue( [ false ] );

		render( <Transactions /> );

		expect(
			screen.queryByRole( 'link', { name: /In-Person Payments/i } )
		).not.toBeInTheDocument();

		expect(
			screen.queryByText( new RegExp( 'The setting is not applied to' ) )
		).not.toBeInTheDocument();
	} );

	it( 'display support email and phone inputs', async () => {
		render( <Transactions /> );
		expect(
			screen.getByLabelText( 'Support phone number' )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Support email' ) ).toBeInTheDocument();
	} );
} );
