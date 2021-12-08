/**
 * Internal dependencies
 */
import WCPayAPI from '..';
import request from 'wcpay/checkout/blocks/request';
import {
	getPaymentRequestData,
	getPaymentRequestAjaxURL,
} from 'wcpay/payment-request/utils';

jest.mock( 'wcpay/checkout/blocks/request', () => jest.fn() );
jest.mock( 'wcpay/payment-request/utils', () => ( {
	getPaymentRequestData: jest.fn(),
	getPaymentRequestAjaxURL: jest.fn(),
} ) );

describe( 'WCPayAPI', () => {
	test( 'initializes platform checkout using expected params', () => {
		getPaymentRequestAjaxURL.mockReturnValue( 'https://example.org/' );
		getPaymentRequestData.mockReturnValue( {
			checkout: 'foo',
		} );

		const api = new WCPayAPI( {}, request );
		api.initPlatformCheckout();

		expect( request ).toHaveBeenLastCalledWith( 'https://example.org/', {
			_wpnonce: 'foo',
		} );
	} );
} );
