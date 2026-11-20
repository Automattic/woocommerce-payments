/* eslint-disable testing-library/no-wait-for-multiple-assertions */
/**
 * External dependencies
 */
import { waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { expressCheckoutIframe } from '../express-checkout-iframe';
import WCPayAPI from 'wcpay/checkout/api';
import { getConfig } from 'utils/checkout';
import { initWooPay } from 'wcpay/checkout/woopay/init-woopay';
import { showErrorMessage } from 'wcpay/checkout/woopay/express-button/utils';

jest.mock( 'utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/woopay/init-woopay', () => ( {
	initWooPay: jest.fn(),
} ) );

jest.mock( 'wcpay/checkout/woopay/express-button/utils', () => ( {
	...jest.requireActual( 'wcpay/checkout/woopay/express-button/utils' ),
	showErrorMessage: jest.fn(),
} ) );

jest.mock( 'tracks', () => ( {
	getTracksIdentity: jest
		.fn()
		.mockReturnValue( Promise.resolve( undefined ) ),
} ) );

describe( 'expressCheckoutIframe', () => {
	const api = new WCPayAPI( {}, jest.fn() );

	beforeEach( () => {
		getConfig.mockReturnValue( 'http://example.com' );
	} );

	afterEach( () => {
		// A failed WooPay init closes the iframe itself.
		document.querySelector( '.woopay-otp-iframe-wrapper' )?.remove();
		jest.clearAllMocks();
	} );

	test( 'should open the iframe', async () => {
		expressCheckoutIframe( api, null, '#email' );

		await waitFor( () => {
			const woopayIframe = document.querySelector( 'iframe' );

			expect( woopayIframe.className ).toContain( 'woopay-otp-iframe' );
			expect( woopayIframe.src ).toContain( 'http://example.com/otp/' );
		} );
	} );

	describe( 'when an email input is present', () => {
		beforeEach( () => {
			const emailInput = document.createElement( 'input' );
			emailInput.setAttribute( 'id', 'email' );
			emailInput.value = 'test@example.com';
			document.body.appendChild( emailInput );
		} );

		afterEach( () => {
			document.body.removeChild( document.querySelector( '#email' ) );
		} );

		test( 'should open the iframe when an email is present', async () => {
			expressCheckoutIframe( api, null, '#email' );

			await waitFor( () => {
				const woopayIframe = document.querySelector( 'iframe' );

				expect( woopayIframe.className ).toContain(
					'woopay-otp-iframe'
				);
				expect( woopayIframe.src ).toContain(
					'http://example.com/otp/'
				);
				expect( woopayIframe.src ).toContain(
					'email=test%40example.com'
				);
			} );
		} );
	} );

	describe( 'pay for order flow is used', () => {
		let oldWcpayConfig;
		let oldWcpayCustomerData;
		beforeEach( () => {
			oldWcpayConfig = window.wcpayConfig;
			oldWcpayCustomerData = window.wcpayCustomerData;
			window.wcpayConfig = {
				pay_for_order: 'true',
			};
			window.wcpayCustomerData = {
				email: 'payfororder@example.com',
			};
		} );

		afterEach( () => {
			window.wcpayConfig = oldWcpayConfig;
			window.wcpayCustomerData = oldWcpayCustomerData;
		} );

		test( 'should open the iframe when an email is present', async () => {
			expressCheckoutIframe( api, null, '#email' );

			await waitFor( () => {
				const woopayIframe = document.querySelector( 'iframe' );

				expect( woopayIframe.className ).toContain(
					'woopay-otp-iframe'
				);
				expect( woopayIframe.src ).toContain(
					'http://example.com/otp/'
				);
				expect( woopayIframe.src ).toContain(
					'email=payfororder%40example.com'
				);
			} );
		} );

		describe( 'when the store refuses to start WooPay', () => {
			const STORE_MESSAGE =
				'WooPay couldn’t load this order. Please reload the page and try again.';

			const redirectToWooPay = async ( refusal ) => {
				initWooPay.mockReturnValue( Promise.resolve( refusal ) );
				expressCheckoutIframe( api, 'pay_for_order', '#email' );
				await waitFor( () =>
					expect(
						document.querySelector( '.woopay-otp-iframe' )
					).not.toBeNull()
				);

				window.dispatchEvent(
					new MessageEvent( 'message', {
						origin: 'http://example.com',
						data: { action: 'redirect_to_woopay' },
					} )
				);
			};

			test( "shows the store's message when WooPay can't load the order", async () => {
				await redirectToWooPay( {
					success: false,
					data: STORE_MESSAGE,
					code: 'woopay_pay_for_order_unavailable',
				} );

				await waitFor( () => {
					expect( showErrorMessage ).toHaveBeenCalledWith(
						'pay_for_order',
						'WooPay couldn’t load this order. Please reload the page and try again.'
					);
				} );
			} );

			test( 'shows the generic message for other refusals', async () => {
				await redirectToWooPay( {
					success: false,
					data: 'You aren’t authorized to do that.',
					code: 'woopay_pay_for_order_not_authorized',
				} );

				await waitFor( () => {
					expect( showErrorMessage ).toHaveBeenCalledWith(
						'pay_for_order',
						'WooPay is unavailable at this time. Sorry for the inconvenience.'
					);
				} );
			} );
		} );

		test( 'should handle missing email in wcpayCustomerData', async () => {
			// Remove email from customer data
			window.wcpayCustomerData = {};

			expressCheckoutIframe( api, null, '#email' );

			await waitFor( () => {
				const woopayIframe = document.querySelector( 'iframe' );
				expect( woopayIframe.src ).not.toContain( 'email=' );
			} );
		} );
	} );
} );
