/**
 * Internal dependencies
 */
import { expressCheckoutIframe } from '../express-checkout-iframe';
import WCPayAPI from 'wcpay/checkout/api';
import { getWooPayExpressData } from 'wcpay/checkout/platform-checkout/express-button/utils';

jest.mock( 'wcpay/checkout/platform-checkout/express-button/utils', () => ( {
	getWooPayExpressData: jest.fn(),
} ) );

describe( 'expressCheckoutIframe', () => {
	const api = new WCPayAPI( {}, jest.fn() );

	test( 'should open the iframe', () => {
		getWooPayExpressData.mockReturnValue( 'http://example.com' );

		expressCheckoutIframe( api );

		const woopayIframe = document.querySelector( 'iframe' );

		expect( woopayIframe.className ).toContain(
			'platform-checkout-otp-iframe'
		);
		expect( woopayIframe.src ).toContain( 'http://example.com/otp/' );
	} );
} );
