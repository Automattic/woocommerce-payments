/**
 * Internal dependencies
 */
import WCPayAPI from '..';
import request from 'wcpay/checkout/utils/request';
import { buildAjaxURL } from 'wcpay/payment-request/utils';
import { getConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/checkout/utils/request', () => jest.fn() );
jest.mock( 'wcpay/payment-request/utils', () => ( {
	buildAjaxURL: jest.fn(),
} ) );
jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

describe( 'WCPayAPI', () => {
	test( 'initializes platform checkout using config params', () => {
		buildAjaxURL.mockReturnValue( 'https://example.org/' );
		getConfig.mockReturnValue( 'foo' );

		const api = new WCPayAPI( {}, request );
		api.initPlatformCheckout( 'foo@bar.com', 'qwerty123' );

		expect( request ).toHaveBeenLastCalledWith( 'https://example.org/', {
			_wpnonce: 'foo',
			email: 'foo@bar.com',
			return_url: 'http://localhost/',
			user_session: 'qwerty123',
		} );
	} );
} );
