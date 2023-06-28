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
	useGetAvailablePaymentMethodIds,
	usePaymentRequestEnabledSettings,
	useWooPayEnabledSettings,
} from 'wcpay/data';
import WCPaySettingsContext from '../../wcpay-settings-context';

jest.mock( 'wcpay/data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	useWooPayEnabledSettings: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
} ) );

const getMockPaymentRequestEnabledSettings = (
	isEnabled,
	updateIsPaymentRequestEnabledHandler
) => [ isEnabled, updateIsPaymentRequestEnabledHandler ];

const getMockWooPayEnabledSettings = (
	isEnabled,
	updateIsWooPayEnabledHandler
) => [ isEnabled, updateIsWooPayEnabledHandler ];

describe( 'ExpressCheckout', () => {
	beforeEach( () => {
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings( false, jest.fn() )
		);
		useWooPayEnabledSettings.mockReturnValue(
			getMockWooPayEnabledSettings( false, jest.fn() )
		);
	} );

	it( 'should dispatch enabled status update if express checkout is being toggled', async () => {
		const updateIsWooPayEnabledHandler = jest.fn();
		const updateIsPaymentRequestEnabledHandler = jest.fn();

		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		useWooPayEnabledSettings.mockReturnValue(
			getMockWooPayEnabledSettings( true, updateIsWooPayEnabledHandler )
		);
		usePaymentRequestEnabledSettings.mockReturnValue(
			getMockPaymentRequestEnabledSettings(
				false,
				updateIsPaymentRequestEnabledHandler
			)
		);

		const context = { featureFlags: { woopay: true } };

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		userEvent.click( screen.getByLabelText( 'WooPay' ) );

		expect( updateIsWooPayEnabledHandler ).toHaveBeenCalledWith( false );
	} );

	it( 'has the correct href links to the express checkout settings pages', async () => {
		const context = { featureFlags: { woopay: true } };

		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		const [
			woopayCheckbox,
			paymentRequestCheckbox,
		] = screen.getAllByRole( 'link', { name: 'Customize' } );

		expect( woopayCheckbox ).toHaveAttribute(
			'href',
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=woopay'
		);

		expect( paymentRequestCheckbox ).toHaveAttribute(
			'href',
			'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&method=payment_request'
		);
	} );

	it( 'hide link payment if card payment method is inactive', async () => {
		const context = { featureFlags: { woopay: true } };
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'link' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect( screen.queryByText( 'Link by Stripe' ) ).toBeNull();
	} );

	it( 'show link payment if card payment method is active', async () => {
		const context = { featureFlags: { woopay: true } };
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect( screen.getByLabelText( 'Link by Stripe' ) ).toBeInTheDocument();
	} );

	it( 'test stripe link checkbox checked', async () => {
		const context = { featureFlags: { woopay: true } };
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		const container = render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);
		const linkCheckbox = container.getByLabelText( 'Link by Stripe' );
		expect( linkCheckbox ).toBeChecked();
	} );

	it( 'test stripe link checkbox not checked', async () => {
		const context = { featureFlags: { woopay: true } };
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		const container = render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);
		const linkCheckbox = container.getByLabelText( 'Link by Stripe' );
		expect( linkCheckbox ).not.toBeChecked();
	} );

	it( 'should prevent enabling both Link and WooPay at the same time', async () => {
		const updateIsWooPayEnabledHandler = jest.fn();
		useWooPayEnabledSettings.mockReturnValue(
			getMockWooPayEnabledSettings( false, updateIsWooPayEnabledHandler )
		);
		const context = { featureFlags: { woopay: true } };
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.queryByText(
				'WooPay cannot be enabled at checkout. Click to expand.'
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'Link by Stripe cannot be enabled at checkout. Click to expand.'
			)
		).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'Link by Stripe' ) ).toBeChecked();
	} );
} );
