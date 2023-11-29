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
import request from 'wcpay/checkout/utils/request';
import { getConfig } from 'utils/checkout';
import wcpayTracks from 'tracks';
import useExpressCheckoutProductHandler from '../use-express-checkout-product-handler';

jest.mock( 'wcpay/checkout/utils/request', () => ( {
	__esModule: true,
	default: jest.fn( () => Promise.resolve( {} ) ),
} ) );

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

jest.spyOn( window, 'alert' ).mockImplementation( () => {} );

global.fetch = jest.fn( () => Promise.resolve( { json: () => ( {} ) } ) );

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

	test( 'does not prefetch session data by default', async () => {
		getConfig.mockImplementation( ( v ) => {
			switch ( v ) {
				case 'wcAjaxUrl':
					return 'woopay.url';
				case 'woopaySessionNonce':
					return 'sessionnonce';
				case 'billing_email':
					return 'test@test.com';
				case 'key':
					return 'testkey';
				case 'order_id':
					return 1;
				default:
					return 'foo';
			}
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

		await waitFor( () => {
			expect( request ).not.toHaveBeenCalled();
			expect( expressCheckoutIframe ).not.toHaveBeenCalled();
		} );
	} );

	test( 'should not request session data on button click', async () => {
		getConfig.mockImplementation( ( v ) => {
			switch ( v ) {
				case 'wcAjaxUrl':
					return 'woopay.url';
				case 'woopaySessionNonce':
					return 'sessionnonce';
				case 'billing_email':
					return 'test@test.com';
				case 'key':
					return 'testkey';
				case 'order_id':
					return 1;
				default:
					return 'foo';
			}
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

		await waitFor( () => {
			expect( request ).not.toHaveBeenCalled();
			expect( expressCheckoutIframe ).not.toHaveBeenCalled();
		} );
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

	test( 'should not call `expressCheckoutIframe` or request session data on button click when `isPreview` is true', async () => {
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

		await waitFor( () => {
			expect( request ).not.toHaveBeenCalled();
			expect( expressCheckoutIframe ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'Product Page', () => {
		test( 'does not prefetch session data by default', async () => {
			render(
				<WoopayExpressCheckoutButton
					isPreview={ false }
					buttonSettings={ buttonSettings }
					api={ api }
					isProductPage={ true }
					emailSelector="#email"
				/>
			);

			await waitFor( () => {
				expect( request ).not.toHaveBeenCalled();
			} );
		} );

		test( 'should show an alert when clicking the button when add to cart button is disabled', () => {
			getConfig.mockImplementation( ( v ) => {
				return v === 'isWoopayFirstPartyAuthEnabled' ? false : 'foo';
			} );
			useExpressCheckoutProductHandler.mockImplementation( () => ( {
				addToCart: mockAddToCart,
			} ) );

			// Add a disabled add to cart button to the DOM.
			const addToCartButton = document.createElement( 'button' );
			addToCartButton.classList.add( 'single_add_to_cart_button' );
			addToCartButton.classList.add( 'disabled' );
			addToCartButton.classList.add( 'wc-variation-selection-needed' );
			document.body.appendChild( addToCartButton );

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

			expect( window.alert ).toBeCalledWith(
				'Please select your product options before proceeding.'
			);

			document.body.removeChild( addToCartButton );
		} );

		test( 'call `addToCart` and `expressCheckoutIframe` on express button click on product page', async () => {
			getConfig.mockImplementation( ( v ) => {
				return v === 'isWoopayFirstPartyAuthEnabled' ? false : 'foo';
			} );
			useExpressCheckoutProductHandler.mockImplementation( () => ( {
				addToCart: mockAddToCart,
				getProductData: jest.fn().mockReturnValue( {} ),
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
