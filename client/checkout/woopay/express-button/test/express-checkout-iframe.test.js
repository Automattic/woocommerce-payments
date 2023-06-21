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

describe( 'expressCheckoutIframe', () => {
	const api = new WCPayAPI( {}, jest.fn() );

	beforeEach( () => {
		getConfig.mockReturnValue( 'http://example.com' );
	} );

	test( 'should open the iframe', async () => {
		expressCheckoutIframe( api, null, '#email' );

		await waitFor( () => {
			const woopayIframe = document.querySelector( 'iframe' );

			expect( woopayIframe.className ).toContain( 'woopay-otp-iframe' );
			expect( woopayIframe.src ).toContain( 'http://example.com/otp/' );
		} );
	} );
} );
