/** @format **/

/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import ExpressCheckout from '..';
import {
	useEnabledPaymentMethodIds,
	usePaymentRequestEnabledSettings,
	usePlatformCheckoutEnabledSettings,
} from 'wcpay/data';
import WCPaySettingsContext from '../../wcpay-settings-context';

jest.mock( 'wcpay/data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	usePlatformCheckoutEnabledSettings: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
} ) );

const getMockPaymentRequestEnabledSettings = (
	isEnabled,
	updateIsPaymentRequestEnabledHandler
) => [ isEnabled, updateIsPaymentRequestEnabledHandler ];

const getMockPlatformCheckoutEnabledSettings = (
	isEnabled,
	updateIsPlatformCheckoutEnabledHandler
) => [ isEnabled, updateIsPlatformCheckoutEnabledHandler ];

describe( 'ExpressCheckout', () => {
	beforeEach( () => {
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( false, jest.fn() )
		);
		usePlatformCheckoutEnabledSettings.mockReturnValue(
			getMockPlatformCheckoutEnabledSettings( false, jest.fn() )
		);
	} );

	it( 'should dispatch enabled status update if express checkout is being toggled', async () => {
		const updateIsPlatformCheckoutEnabledHandler = jest.fn();
		const updateIsPaymentRequestEnabledHandler = jest.fn();

		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		usePlatformCheckoutEnabledSettings.mockReturnValue(
			getMockPlatformCheckoutEnabledSettings(
				false,
				updateIsPlatformCheckoutEnabledHandler
			)
		);
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings(
				false,
				updateIsPaymentRequestEnabledHandler
			)
		);

		const context = { featureFlags: { platformCheckout: true } };

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		const [
			platformCheckoutCheckbox,
			paymentRequestCheckbox,
		] = screen.queryAllByRole( 'checkbox' );

		userEvent.click( platformCheckoutCheckbox );
		userEvent.click( paymentRequestCheckbox );

		expect( updateIsPlatformCheckoutEnabledHandler ).toHaveBeenCalledWith(
			true
		);
		expect( updateIsPaymentRequestEnabledHandler ).toHaveBeenCalledWith(
			true
		);
	} );

	it( 'has the correct href links to the express checkout settings pages', async () => {
		const context = { featureFlags: { platformCheckout: true } };

		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		const [
			platformCheckoutCheckbox,
			paymentRequestCheckbox,
		] = screen.getAllByRole( 'link', { name: 'Customize' } );

		expect( platformCheckoutCheckbox ).toHaveAttribute(
			'href',
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=platform_checkout'
		);

		expect( paymentRequestCheckbox ).toHaveAttribute(
			'href',
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=payment_request'
		);
	} );

	it( 'hide link payment if card payment method is inactive', async () => {
		const context = { featureFlags: { platformCheckout: true } };

		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'link' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect( screen.queryByText( 'Stripe Link' ) ).toBeNull();
	} );

	it( 'show link payment if card payment method is active', async () => {
		const context = { featureFlags: { platformCheckout: true } };

		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect( screen.getByLabelText( 'Stripe Link' ) ).toBeInTheDocument();
	} );

	it( 'test stripe link checkbox checked', async () => {
		const context = { featureFlags: { platformCheckout: true } };
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		const container = render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);
		const linkCheckbox = container.getByLabelText( 'Stripe Link' );
		expect( linkCheckbox ).toBeChecked();
	} );

	it( 'test stripe link checkbox not checked', async () => {
		const context = { featureFlags: { platformCheckout: true } };
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		const container = render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);
		const linkCheckbox = container.getByLabelText( 'Stripe Link' );
		expect( linkCheckbox ).not.toBeChecked();
	} );
} );
