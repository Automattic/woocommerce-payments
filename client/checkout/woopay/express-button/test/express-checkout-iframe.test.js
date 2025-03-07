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

jest.mock( 'utils/checkout', () => ( {
	getConfig: jest.fn(),
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
		document.body.removeChild(
			document.querySelector( '.woopay-otp-iframe-wrapper' )
		);
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
