/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ExpressCheckoutSettingsNotices from '../express-checkout-settings-notices';
import {
	useWooPayEnabledSettings,
	usePaymentRequestEnabledSettings,
	useAmazonPayEnabledSettings,
} from 'wcpay/data';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

jest.mock( 'wcpay/data', () => ( {
	useWooPayEnabledSettings: jest.fn(),
	usePaymentRequestEnabledSettings: jest.fn(),
	useAmazonPayEnabledSettings: jest.fn(),
} ) );

const renderWithSettingsProvider = (
	ui,
	featureFlags = { woopay: true, amazonPay: true }
) =>
	render(
		<WCPaySettingsContext.Provider value={ { featureFlags } }>
			{ ui }
		</WCPaySettingsContext.Provider>
	);

describe( 'ExpressCheckoutSettingsNotices', () => {
	beforeEach( () => {
		useWooPayEnabledSettings.mockReturnValue( [ false ] );
		usePaymentRequestEnabledSettings.mockReturnValue( [ false ] );
		useAmazonPayEnabledSettings.mockReturnValue( [ false ] );
	} );

	it( 'renders nothing when no other methods are enabled', () => {
		const { container } = renderWithSettingsProvider(
			<ExpressCheckoutSettingsNotices currentMethod="woopay" />
		);

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders notices when one other method is enabled (singular button)', () => {
		usePaymentRequestEnabledSettings.mockReturnValue( [ true ] );

		renderWithSettingsProvider(
			<ExpressCheckoutSettingsNotices currentMethod="woopay" />
		);

		expect(
			screen.getByText(
				'These settings will also apply to the Apple Pay / Google Pay button on your store.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Some appearance settings may be overridden in the express payment section of the Cart & Checkout blocks.'
			)
		).toBeInTheDocument();
	} );

	it( 'renders notices when two other methods are enabled (plural buttons with "and")', () => {
		useWooPayEnabledSettings.mockReturnValue( [ true ] );
		usePaymentRequestEnabledSettings.mockReturnValue( [ true ] );

		renderWithSettingsProvider(
			<ExpressCheckoutSettingsNotices currentMethod="amazon_pay" />
		);

		expect(
			screen.getByText(
				'These settings will also apply to the WooPay and Apple Pay / Google Pay buttons on your store.'
			)
		).toBeInTheDocument();
	} );

	it( 'renders notices when all other methods are enabled', () => {
		useWooPayEnabledSettings.mockReturnValue( [ true ] );
		usePaymentRequestEnabledSettings.mockReturnValue( [ true ] );
		useAmazonPayEnabledSettings.mockReturnValue( [ true ] );

		renderWithSettingsProvider(
			<ExpressCheckoutSettingsNotices currentMethod="woopay" />
		);

		expect(
			screen.getByText(
				'These settings will also apply to the Apple Pay / Google Pay and Amazon Pay buttons on your store.'
			)
		).toBeInTheDocument();
	} );

	it( 'respects WooPay feature flag when disabled', () => {
		useWooPayEnabledSettings.mockReturnValue( [ true ] );
		usePaymentRequestEnabledSettings.mockReturnValue( [ true ] );

		renderWithSettingsProvider(
			<ExpressCheckoutSettingsNotices currentMethod="amazon_pay" />,
			{ woopay: false, amazonPay: true }
		);

		expect(
			screen.getByText(
				'These settings will also apply to the Apple Pay / Google Pay button on your store.'
			)
		).toBeInTheDocument();
	} );

	it( 'respects Amazon Pay feature flag when disabled', () => {
		useAmazonPayEnabledSettings.mockReturnValue( [ true ] );
		usePaymentRequestEnabledSettings.mockReturnValue( [ true ] );

		renderWithSettingsProvider(
			<ExpressCheckoutSettingsNotices currentMethod="woopay" />,
			{ woopay: true, amazonPay: false }
		);

		expect(
			screen.getByText(
				'These settings will also apply to the Apple Pay / Google Pay button on your store.'
			)
		).toBeInTheDocument();
	} );
} );
