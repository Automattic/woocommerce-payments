/**
 * Internal dependencies
 */
import WCPayAPI from '..';
import request from 'wcpay/checkout/blocks/request';
import { buildAjaxURL } from 'wcpay/payment-request/utils';
import { getConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/checkout/blocks/request', () => jest.fn() );
jest.mock( 'wcpay/payment-request/utils', () => ( {
	buildAjaxURL: jest.fn(),
} ) );
jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

describe( 'WCPayAPI', () => {
	test( 'initializes platform checkout using expected params', () => {
		buildAjaxURL.mockReturnValue( 'https://example.org/' );
		getConfig.mockReturnValue( 'foo' );

		const api = new WCPayAPI( {}, request );
		api.initPlatformCheckout( 'qwerty123' );

		expect( request ).toHaveBeenLastCalledWith( 'https://example.org/', {
			_wpnonce: 'foo',
			user_session: 'qwerty123',
		} );
	} );
} );
