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
	usePaymentRequestEnabledSettings,
	usePlatformCheckoutEnabledSettings,
} from 'wcpay/data';

// Mock platform checkout feature flag.
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn().mockImplementation( () => ( {
		getIsPlatformCheckoutFeatureFlagEnabled: jest
			.fn()
			.mockReturnValue( true ),
	} ) ),
} ) );

jest.mock( 'wcpay/data', () => ( {
	usePaymentRequestEnabledSettings: jest.fn(),
	usePlatformCheckoutEnabledSettings: jest.fn(),
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

		render( <ExpressCheckout /> );

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
		render( <ExpressCheckout /> );

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
} );
