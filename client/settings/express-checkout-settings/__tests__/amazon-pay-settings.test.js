/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import AmazonPaySettings from '../amazon-pay-settings';
import { useAmazonPayEnabledSettings, useAmazonPayLocations } from 'wcpay/data';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

jest.mock( 'wcpay/data', () => ( {
	useAmazonPayEnabledSettings: jest.fn(),
	useAmazonPayLocations: jest.fn(),
	usePaymentRequestButtonSize: jest.fn().mockReturnValue( [ 'medium' ] ),
	useWooPayEnabledSettings: jest.fn().mockReturnValue( [ false ] ),
	usePaymentRequestEnabledSettings: jest.fn().mockReturnValue( [ false ] ),
} ) );

const renderWithSettingsProvider = ( ui ) =>
	render(
		<WCPaySettingsContext.Provider
			value={ { featureFlags: { woopay: false, amazonPay: false } } }
		>
			{ ui }
		</WCPaySettingsContext.Provider>
	);

describe( 'AmazonPaySettings', () => {
	beforeEach( () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true, jest.fn() ] );
		useAmazonPayLocations.mockReturnValue( [
			[ 'product', 'cart', 'checkout' ],
			jest.fn(),
		] );
	} );

	it( 'triggers the update handler when the enable checkbox is clicked', async () => {
		const updateIsAmazonPayEnabledHandler = jest.fn();
		useAmazonPayEnabledSettings.mockReturnValue( [
			true,
			updateIsAmazonPayEnabledHandler,
		] );

		renderWithSettingsProvider( <AmazonPaySettings section="enable" /> );

		expect( updateIsAmazonPayEnabledHandler ).not.toHaveBeenCalled();

		expect(
			screen.getByLabelText(
				'Enable Amazon Pay as an express payment button'
			)
		).toBeChecked();

		userEvent.click(
			screen.getByLabelText(
				'Enable Amazon Pay as an express payment button'
			)
		);

		expect( updateIsAmazonPayEnabledHandler ).toHaveBeenCalledWith( false );
	} );

	it( 'renders location checkboxes as checked when locations are enabled', () => {
		useAmazonPayLocations.mockReturnValue( [
			[ 'product', 'cart', 'checkout' ],
			jest.fn(),
		] );

		renderWithSettingsProvider( <AmazonPaySettings section="enable" /> );

		expect(
			screen.getByLabelText( 'Show on checkout page' )
		).toBeChecked();
		expect( screen.getByLabelText( 'Show on product page' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Show on cart page' ) ).toBeChecked();
	} );

	it( 'renders location checkboxes as unchecked when locations are disabled', () => {
		useAmazonPayLocations.mockReturnValue( [ [], jest.fn() ] );

		renderWithSettingsProvider( <AmazonPaySettings section="enable" /> );

		expect(
			screen.getByLabelText( 'Show on checkout page' )
		).not.toBeChecked();
		expect(
			screen.getByLabelText( 'Show on product page' )
		).not.toBeChecked();
		expect(
			screen.getByLabelText( 'Show on cart page' )
		).not.toBeChecked();
	} );

	it( 'disables location checkboxes when Amazon Pay is disabled', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ false, jest.fn() ] );

		renderWithSettingsProvider( <AmazonPaySettings section="enable" /> );

		expect(
			screen.getByLabelText(
				'Enable Amazon Pay as an express payment button'
			)
		).not.toBeChecked();
		expect(
			screen.getByLabelText( 'Show on checkout page' )
		).toBeDisabled();
		expect(
			screen.getByLabelText( 'Show on product page' )
		).toBeDisabled();
		expect( screen.getByLabelText( 'Show on cart page' ) ).toBeDisabled();
	} );

	it( 'triggers the location update handler when location checkboxes are clicked', async () => {
		const updateAmazonPayLocationsHandler = jest.fn();
		useAmazonPayLocations.mockReturnValue( [
			[ 'product' ],
			updateAmazonPayLocationsHandler,
		] );

		renderWithSettingsProvider( <AmazonPaySettings section="enable" /> );

		expect( updateAmazonPayLocationsHandler ).not.toHaveBeenCalled();

		userEvent.click( screen.getByLabelText( 'Show on checkout page' ) );
		expect( updateAmazonPayLocationsHandler ).toHaveBeenLastCalledWith(
			'checkout',
			true
		);

		userEvent.click( screen.getByLabelText( 'Show on product page' ) );
		expect( updateAmazonPayLocationsHandler ).toHaveBeenLastCalledWith(
			'product',
			false
		);

		userEvent.click( screen.getByLabelText( 'Show on cart page' ) );
		expect( updateAmazonPayLocationsHandler ).toHaveBeenLastCalledWith(
			'cart',
			true
		);
	} );

	it( 'renders general settings section with button size options', () => {
		renderWithSettingsProvider( <AmazonPaySettings section="general" /> );

		expect( screen.queryByText( 'Button size' ) ).toBeInTheDocument();

		const radioButtons = screen.queryAllByRole( 'radio' );
		expect( radioButtons ).toHaveLength( 3 );
	} );
} );
