/**
 * Internal dependencies
 */
import WCPayAPI from '..';
import request from 'wcpay/checkout/utils/request';
import { buildAjaxURL } from 'wcpay/payment-request/utils';
import { getConfig } from 'wcpay/utils/checkout';

jest.mock( 'wcpay/checkout/utils/request', () =>
	jest.fn( () => Promise.resolve( {} ).finally( () => {} ) )
);
jest.mock( 'wcpay/payment-request/utils', () => ( {
	buildAjaxURL: jest.fn(),
} ) );
jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

describe( 'WCPayAPI', () => {
	test( 'does not initialize woopay if already requesting', async () => {
		buildAjaxURL.mockReturnValue( 'https://example.org/' );
		getConfig.mockReturnValue( 'foo' );

		const api = new WCPayAPI( {}, request );
		api.isWooPayRequesting = true;
		await api.initWooPay( 'foo@bar.com', 'qwerty123' );

		expect( request ).not.toHaveBeenCalled();
		expect( api.isWooPayRequesting ).toBe( true );
	} );

	test( 'initializes woopay using config params', async () => {
		buildAjaxURL.mockReturnValue( 'https://example.org/' );
		getConfig.mockReturnValue( 'foo' );

		const api = new WCPayAPI( {}, request );
		await api.initWooPay( 'foo@bar.com', 'qwerty123' );

		expect( request ).toHaveBeenLastCalledWith( 'https://example.org/', {
			_wpnonce: 'foo',
			email: 'foo@bar.com',
			user_session: 'qwerty123',
		} );
		expect( api.isWooPayRequesting ).toBe( false );
	} );
} );
