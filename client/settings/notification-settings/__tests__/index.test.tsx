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
import { useAccountCommunicationsEmail, useGetSavingError } from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useAccountCommunicationsEmail: jest.fn(),
	useGetSavingError: jest.fn(),
} ) );

const mockUseAccountCommunicationsEmail = useAccountCommunicationsEmail as jest.MockedFunction<
	typeof useAccountCommunicationsEmail
>;
const mockUseGetSavingError = useGetSavingError as jest.MockedFunction<
	typeof useGetSavingError
>;

describe( 'NotificationSettings', () => {
	beforeEach( () => {
		mockUseAccountCommunicationsEmail.mockReturnValue( [
			'test@example.com',
			jest.fn(),
		] );
		mockUseGetSavingError.mockReturnValue( null );
	} );

	it( 'renders the notification settings section', () => {
		render( <NotificationSettings /> );

		expect( screen.getByLabelText( 'Email address' ) ).toBeInTheDocument();
	} );

	it( 'renders with the communications email input', () => {
		const testEmail = 'communications@example.com';
		mockUseAccountCommunicationsEmail.mockReturnValue( [
			testEmail,
			jest.fn(),
		] );

		render( <NotificationSettings /> );

		expect( screen.getByDisplayValue( testEmail ) ).toBeInTheDocument();
	} );
} );

describe( 'NotificationSettingsDescription', () => {
	it( 'renders the title', () => {
		render( <NotificationSettingsDescription /> );

		expect(
			screen.getByRole( 'heading', { name: 'Account notifications' } )
		).toBeInTheDocument();
	} );

	it( 'renders the description text', () => {
		render( <NotificationSettingsDescription /> );

		expect(
			screen.getByText(
				'Receive important notifications about your WooPayments account.'
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
			'https://woocommerce.com/document/woopayments/settings-guide/#account-notifications'
		);
	} );
} );
