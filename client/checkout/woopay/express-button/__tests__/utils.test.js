/**
 * Internal dependencies
 */
import { getSessionErrorMessage, showErrorMessage } from '../utils';

jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn( () => 'config-value' ),
} ) );

const STORE_MESSAGE = 'WooPay couldn’t load this order.';

describe( 'showErrorMessage', () => {
	const createNotice = jest.fn();

	beforeEach( () => {
		global.wcSettings = { wcBlocksConfig: {} };
		global.wp = {
			data: { dispatch: jest.fn( () => ( { createNotice } ) ) },
		};
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => ( { success: false } ) } )
		);
	} );

	afterEach( () => {
		delete global.wcSettings;
		delete global.wp;
		jest.clearAllMocks();
	} );

	test( 'adds the notice to the Blocks notice store on a Blocks checkout', () => {
		showErrorMessage( 'checkout', STORE_MESSAGE );

		expect( createNotice ).toHaveBeenCalledWith(
			'error',
			'WooPay couldn’t load this order.',
			{ context: 'wc/checkout' }
		);
		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( 'asks the store for a page notice on the pay-for-order page, which WooCommerce renders with its shortcode', () => {
		showErrorMessage( 'pay_for_order', STORE_MESSAGE );

		expect( createNotice ).not.toHaveBeenCalled();
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		expect( global.fetch.mock.calls[ 0 ][ 1 ].body.get( 'message' ) ).toBe(
			'WooPay couldn’t load this order.'
		);
	} );
} );

describe( 'getSessionErrorMessage', () => {
	test( "returns the store's message from a refused request", () => {
		expect(
			getSessionErrorMessage( { success: false, data: STORE_MESSAGE } )
		).toBe( 'WooPay couldn’t load this order.' );
	} );

	test.each( [
		[ 'a successful response', { success: true, data: 'text' } ],
		[ 'a non-text payload', { success: false, data: { code: 'x' } } ],
		[ 'no response', undefined ],
	] )( 'returns an empty string for %s', ( _, response ) => {
		expect( getSessionErrorMessage( response ) ).toBe( '' );
	} );
} );
