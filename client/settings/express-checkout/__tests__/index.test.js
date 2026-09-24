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
	useWooPayShowIncompatibilityNotice,
	useGetDuplicatedPaymentMethodIds,
	useAmazonPayEnabledSettings,
	useGetPaymentMethodStatuses,
	useManualCapture,
} from 'wcpay/data/settings';
import WCPaySettingsContext from '../../wcpay-settings-context';
import { upeCapabilityStatuses } from 'wcpay/settings/constants';

jest.mock( 'wcpay/data/settings', () => ( {
	useTestMode: jest.fn().mockReturnValue( [] ),
	usePaymentRequestEnabledSettings: jest.fn(),
	useWooPayEnabledSettings: jest.fn(),
	useEnabledPaymentMethodIds: jest.fn(),
	useGetAvailablePaymentMethodIds: jest.fn(),
	useWooPayShowIncompatibilityNotice: jest.fn(),
	useGetDuplicatedPaymentMethodIds: jest.fn(),
	useAmazonPayEnabledSettings: jest.fn(),
	useGetPaymentMethodStatuses: jest.fn(),
	useManualCapture: jest.fn(),
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
		useAmazonPayEnabledSettings.mockReturnValue( [ false, jest.fn() ] );

		useWooPayShowIncompatibilityNotice.mockReturnValue( false );

		useGetDuplicatedPaymentMethodIds.mockReturnValue( [] );

		useGetPaymentMethodStatuses.mockReturnValue( {
			amazon_pay_payments: upeCapabilityStatuses.ACTIVE,
		} );
		useManualCapture.mockReturnValue( [ false ] );
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

		const context = { accountStatus: {}, featureFlags: { woopay: true } };

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		await userEvent.click( screen.getByLabelText( 'WooPay' ) );

		expect( updateIsWooPayEnabledHandler ).toHaveBeenCalledWith( false );
	} );

	it( 'has the correct href links to the express checkout settings pages', async () => {
		const context = { accountStatus: {}, featureFlags: { woopay: true } };

		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		const [ woopayCheckbox, paymentRequestCheckbox ] = screen.getAllByRole(
			'link',
			{ name: 'Customize' }
		);

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
		const context = { accountStatus: {}, featureFlags: { woopay: true } };
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
		const context = { accountStatus: {}, featureFlags: { woopay: true } };
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
		const context = { accountStatus: {}, featureFlags: { woopay: true } };
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
		const context = { accountStatus: {}, featureFlags: { woopay: true } };
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
		const context = { accountStatus: {}, featureFlags: { woopay: true } };
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card', 'link' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.queryByText(
				'To enable WooPay, you must first disable Link by Stripe.',
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeInTheDocument();
		expect(
			screen.queryByText(
				'To enable Link by Stripe, you must first disable WooPay.',
				{
					ignore: '.a11y-speak-region',
				}
			)
		).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'Link by Stripe' ) ).toBeChecked();
		expect( screen.getByLabelText( 'WooPay' ) ).toBeDisabled();
	} );

	it( 'should prevent enabling Link while WooPay is enabled', async () => {
		useWooPayEnabledSettings.mockReturnValue(
			getMockWooPayEnabledSettings( true, jest.fn() )
		);
		const context = { accountStatus: {}, featureFlags: { woopay: true } };
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ], jest.fn() ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect( screen.getByLabelText( 'Link by Stripe' ) ).toBeDisabled();
		expect( screen.getByLabelText( 'WooPay' ) ).not.toBeDisabled();
		expect(
			screen.queryByText(
				'To enable Link by Stripe, you must first disable WooPay.',
				{
					ignore: '.a11y-speak-region',
				}
			)
		).toBeInTheDocument();
	} );

	describe( 'when both Link and WooPay are already enabled', () => {
		const updateIsWooPayEnabledHandler = jest.fn();
		const updateEnabledMethodIdsHandler = jest.fn();

		beforeEach( () => {
			updateIsWooPayEnabledHandler.mockClear();
			updateEnabledMethodIdsHandler.mockClear();
			useWooPayEnabledSettings.mockReturnValue(
				getMockWooPayEnabledSettings(
					true,
					updateIsWooPayEnabledHandler
				)
			);
			useGetAvailablePaymentMethodIds.mockReturnValue( [
				'link',
				'card',
			] );
			useEnabledPaymentMethodIds.mockReturnValue( [
				[ 'card', 'link' ],
				updateEnabledMethodIdsHandler,
			] );

			render(
				<WCPaySettingsContext.Provider
					value={ {
						accountStatus: {},
						featureFlags: { woopay: true },
					} }
				>
					<ExpressCheckout />
				</WCPaySettingsContext.Provider>
			);
		} );

		it( 'allows disabling Link', async () => {
			const linkCheckbox = screen.getByLabelText( 'Link by Stripe' );
			expect( linkCheckbox ).toBeChecked();
			expect( linkCheckbox ).not.toBeDisabled();

			await userEvent.click( linkCheckbox );

			expect( updateEnabledMethodIdsHandler ).toHaveBeenCalledWith( [
				'card',
			] );
		} );

		it( 'allows disabling WooPay', async () => {
			const wooPayCheckbox = screen.getByLabelText( 'WooPay' );
			expect( wooPayCheckbox ).toBeChecked();
			expect( wooPayCheckbox ).not.toBeDisabled();

			await userEvent.click( wooPayCheckbox );

			expect( updateIsWooPayEnabledHandler ).toHaveBeenCalledWith(
				false
			);
		} );

		it( 'explains the conflict instead of the enable-first warnings', () => {
			expect(
				screen.getAllByText(
					"Link by Stripe and WooPay can't be enabled at the same time. Disable one of them.",
					{ ignore: '.a11y-speak-region' }
				)
			).toHaveLength( 2 );
			expect(
				screen.queryByText(
					'To enable WooPay, you must first disable Link by Stripe.',
					{ ignore: '.a11y-speak-region' }
				)
			).not.toBeInTheDocument();
			expect(
				screen.queryByText(
					'To enable Link by Stripe, you must first disable WooPay.',
					{ ignore: '.a11y-speak-region' }
				)
			).not.toBeInTheDocument();
		} );
	} );

	it( 'does not block WooPay on a Link setting hidden because card is disabled', () => {
		const context = { accountStatus: {}, featureFlags: { woopay: true } };
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'link' ], jest.fn() ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect( screen.queryByLabelText( 'Link by Stripe' ) ).toBeNull();
		expect( screen.getByLabelText( 'WooPay' ) ).not.toBeDisabled();
		expect(
			screen.queryByText(
				'To enable WooPay, you must first disable Link by Stripe.',
				{ ignore: '.a11y-speak-region' }
			)
		).not.toBeInTheDocument();
	} );

	it( 'should show WooPay incompatibility warning', async () => {
		const updateIsWooPayEnabledHandler = jest.fn();
		useWooPayEnabledSettings.mockReturnValue(
			getMockWooPayEnabledSettings( true, updateIsWooPayEnabledHandler )
		);
		const context = { accountStatus: {}, featureFlags: { woopay: true } };
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		useWooPayShowIncompatibilityNotice.mockReturnValue( true );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.queryByText(
				'One or more of your extensions are incompatible with WooPay.'
			)
		).toBeInTheDocument();
	} );

	it( 'should render Amazon Pay when the feature flag is enabled', () => {
		const context = {
			accountStatus: {},
			featureFlags: { woopay: true, amazonPay: true },
		};
		useGetAvailablePaymentMethodIds.mockReturnValue( [
			'link',
			'card',
			'amazon_pay',
		] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect( screen.getByLabelText( 'Amazon Pay' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'WooPay' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Link by Stripe' ) ).toBeInTheDocument();
	} );

	it( 'should not render Amazon Pay by default', () => {
		const context = {
			accountStatus: {},
			featureFlags: { woopay: true },
		};
		useGetAvailablePaymentMethodIds.mockReturnValue( [ 'link', 'card' ] );
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		render(
			<WCPaySettingsContext.Provider value={ context }>
				<ExpressCheckout />
			</WCPaySettingsContext.Provider>
		);

		expect(
			screen.queryByLabelText( 'Amazon Pay' )
		).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'WooPay' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Link by Stripe' ) ).toBeInTheDocument();
	} );
} );
