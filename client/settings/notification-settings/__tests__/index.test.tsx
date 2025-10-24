/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import NotificationSettings, {
	NotificationSettingsDescription,
} from '../index';
import { useCommunicationsEmail, useGetSavingError } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useCommunicationsEmail: jest.fn(),
	useGetSavingError: jest.fn(),
} ) );

const mockUseCommunicationsEmail = useCommunicationsEmail as jest.MockedFunction<
	typeof useCommunicationsEmail
>;
const mockUseGetSavingError = useGetSavingError as jest.MockedFunction<
	typeof useGetSavingError
>;

describe( 'NotificationSettings', () => {
	beforeEach( () => {
		mockUseCommunicationsEmail.mockReturnValue( [
			'test@example.com',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( null );
	} );

	it( 'renders the notification settings section', () => {
		render( <NotificationSettings /> );

		expect(
			screen.getByLabelText( 'Communications email' )
		).toBeInTheDocument();
	} );

	it( 'renders with the communications email input', () => {
		const testEmail = 'communications@example.com';
		mockUseCommunicationsEmail.mockReturnValue( [ testEmail, jest.fn() ] );

		render( <NotificationSettings /> );

		expect( screen.getByDisplayValue( testEmail ) ).toBeInTheDocument();
	} );
} );

describe( 'NotificationSettingsDescription', () => {
	it( 'renders the title', () => {
		render( <NotificationSettingsDescription /> );

		expect(
			screen.getByRole( 'heading', { name: 'Notifications' } )
		).toBeInTheDocument();
	} );

	it( 'renders the description text', () => {
		render( <NotificationSettingsDescription /> );

		expect(
			screen.getByText(
				'Configure how you receive important alerts about your WooPayments account.'
			)
		).toBeInTheDocument();
	} );

	it( 'renders the learn more link', () => {
		render( <NotificationSettingsDescription /> );

		const link = screen.getByRole( 'link', {
			name: /Learn more/,
		} );
		expect( link ).toBeInTheDocument();
		expect( link ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woopayments/'
		);
	} );
} );
