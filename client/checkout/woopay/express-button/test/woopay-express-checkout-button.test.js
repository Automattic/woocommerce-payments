/**
 * External dependencies
 */
import { screen, render, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { WoopayExpressCheckoutButton } from '../woopay-express-checkout-button';
import { expressCheckoutIframe } from '../express-checkout-iframe';
import WCPayAPI from 'wcpay/checkout/api';
import { getConfig } from 'utils/checkout';
import wcpayTracks from 'tracks';
import useExpressCheckoutProductHandler from '../use-express-checkout-product-handler';

jest.mock( 'utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

jest.mock( '../express-checkout-iframe', () => ( {
	__esModule: true,
	expressCheckoutIframe: jest.fn(),
} ) );

jest.mock( 'tracks', () => ( {
	recordUserEvent: jest.fn(),
} ) );

jest.mock( '../use-express-checkout-product-handler', () => jest.fn() );

describe( 'WoopayExpressCheckoutButton', () => {
	const buttonSettings = {
		type: 'default',
		height: '48px',
		size: 'medium',
		theme: 'dark',
	};
	const mockRequest = jest.fn().mockResolvedValue( true );
	const mockAddToCart = jest.fn().mockResolvedValue( true );
	const api = new WCPayAPI( {}, mockRequest );

	beforeEach( () => {
		expressCheckoutIframe.mockImplementation( () => jest.fn() );
		getConfig.mockReturnValue( 'foo' );
		wcpayTracks.recordUserEvent.mockReturnValue( true );
		wcpayTracks.events = {
			WOOPAY_EXPRESS_BUTTON_OFFERED: 'woopay_express_button_offered',
		};
		useExpressCheckoutProductHandler.mockImplementation( () => ( {
			addToCart: mockAddToCart,
			isAddToCartDisabled: false,
		} ) );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'render the express checkout button', () => {
		render(
			<WoopayExpressCheckoutButton
				isPreview={ false }
				buttonSettings={ buttonSettings }
				api={ api }
				isProductPage={ false }
				emailSelector="#email"
			/>
		);

		expect(
			screen.queryByRole( 'button', { name: 'WooPay' } )
		).toBeInTheDocument();
	} );

	test( 'call `expressCheckoutIframe` on button click when `isPreview` is false', () => {
		getConfig.mockImplementation( ( v ) => {
			return v === 'isWoopayFirstPartyAuthEnabled' ? false : 'foo';
		} );
		render(
			<WoopayExpressCheckoutButton
				isPreview={ false }
				buttonSettings={ buttonSettings }
				api={ api }
				isProductPage={ false }
				emailSelector="#email"
			/>
		);

		const expressButton = screen.queryByRole( 'button', {
			name: 'WooPay',
		} );
		userEvent.click( expressButton );

		expect( expressCheckoutIframe ).toHaveBeenCalledWith(
			api,
			buttonSettings.context,
			'#email'
		);
	} );

	test( 'should not call `expressCheckoutIframe` on button click when `isPreview` is true', () => {
		render(
			<WoopayExpressCheckoutButton
				isPreview={ true }
				buttonSettings={ buttonSettings }
				api={ api }
				isProductPage={ false }
				emailSelector="#email"
			/>
		);

		const expressButton = screen.queryByRole( 'button', {
			name: 'WooPay',
		} );
		userEvent.click( expressButton );

		expect( expressCheckoutIframe ).not.toHaveBeenCalled();
	} );

	describe( 'Product Page', () => {
		test( 'should enable the button when add to cart button is enabled', () => {
			getConfig.mockImplementation( ( v ) => {
				return v === 'isWoopayFirstPartyAuthEnabled' ? false : 'foo';
			} );
			render(
				<WoopayExpressCheckoutButton
					isPreview={ false }
					buttonSettings={ buttonSettings }
					api={ api }
					isProductPage={ true }
					emailSelector="#email"
				/>
			);

			const expressButton = screen.queryByRole( 'button', {
				name: 'WooPay',
			} );
			expect( expressButton ).toBeEnabled();
		} );

		test( 'should disable the button when add to cart button is disabled', () => {
			useExpressCheckoutProductHandler.mockImplementation( () => ( {
				addToCart: mockAddToCart,
				isAddToCartDisabled: true,
			} ) );

			render(
				<WoopayExpressCheckoutButton
					isPreview={ false }
					buttonSettings={ buttonSettings }
					api={ api }
					isProductPage={ true }
					emailSelector="#email"
				/>
			);

			const expressButton = screen.queryByRole( 'button', {
				name: 'WooPay',
			} );
			expect( expressButton ).toBeDisabled();
		} );

		test( 'call `addToCart` and `expressCheckoutIframe` on express button click on product page', async () => {
			getConfig.mockImplementation( ( v ) => {
				return v === 'isWoopayFirstPartyAuthEnabled' ? false : 'foo';
			} );
			useExpressCheckoutProductHandler.mockImplementation( () => ( {
				addToCart: mockAddToCart,
				getProductData: jest.fn().mockReturnValue( {} ),
				isAddToCartDisabled: false,
			} ) );
			render(
				<WoopayExpressCheckoutButton
					isPreview={ false }
					buttonSettings={ buttonSettings }
					api={ api }
					isProductPage={ true }
					emailSelector="#email"
				/>
			);

			const expressButton = screen.queryByRole( 'button', {
				name: 'WooPay',
			} );

			userEvent.click( expressButton );

			expect( mockAddToCart ).toHaveBeenCalled();

			await waitFor( () => {
				expect( expressCheckoutIframe ).toHaveBeenCalledWith(
					api,
					buttonSettings.context,
					'#email'
				);
			} );
		} );

		test( 'do not call `addToCart` on express button click on product page when validation fails', async () => {
			getConfig.mockImplementation( ( v ) => {
				return v === 'isWoopayFirstPartyAuthEnabled' ? false : 'foo';
			} );
			useExpressCheckoutProductHandler.mockImplementation( () => ( {
				addToCart: mockAddToCart,
				getProductData: jest.fn().mockReturnValue( false ),
				isAddToCartDisabled: false,
			} ) );
			render(
				<WoopayExpressCheckoutButton
					isPreview={ false }
					buttonSettings={ buttonSettings }
					api={ api }
					isProductPage={ true }
					emailSelector="#email"
				/>
			);

			const expressButton = screen.queryByRole( 'button', {
				name: 'WooPay',
			} );

			userEvent.click( expressButton );

			expect( mockAddToCart ).not.toHaveBeenCalled();

			await waitFor( () => {
				expect( expressCheckoutIframe ).not.toHaveBeenCalled();
			} );
		} );
	} );
} );
