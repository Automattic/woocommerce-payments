/**
 * External dependencies
 */
import { waitFor } from '@testing-library/react';

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
		document.body.innerHTML = '';
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

	test( 'shows the notice on the pay-for-order page, which WooCommerce renders with its shortcode', async () => {
		document.body.innerHTML =
			'<div class="woocommerce-notices-wrapper"></div>';
		const noticesWrapper = document.querySelector(
			'.woocommerce-notices-wrapper'
		);
		noticesWrapper.scrollIntoView = jest.fn();
		global.fetch.mockImplementation( () =>
			Promise.resolve( {
				json: () => ( {
					success: true,
					data: {
						notice: '<ul class="woocommerce-error"><li>WooPay couldn’t load this order.</li></ul>',
					},
				} ),
			} )
		);

		showErrorMessage( 'pay_for_order', STORE_MESSAGE );

		await waitFor( () => {
			expect( noticesWrapper.textContent ).toBe(
				'WooPay couldn’t load this order.'
			);
		} );
		expect( createNotice ).not.toHaveBeenCalled();
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		expect( global.fetch.mock.calls[ 0 ][ 1 ].body.get( 'message' ) ).toBe(
			'WooPay couldn’t load this order.'
		);
	} );
} );

describe( 'getSessionErrorMessage', () => {
	test( "returns the store's message when WooPay can't load the order", () => {
		expect(
			getSessionErrorMessage( {
				success: false,
				data: STORE_MESSAGE,
				code: 'woopay_pay_for_order_unavailable',
			} )
		).toBe( 'WooPay couldn’t load this order.' );
	} );

	test.each( [
		[
			'an expired nonce',
			{ success: false, data: 'You aren’t authorized to do that.' },
		],
		[
			'a wrong order key',
			{
				success: false,
				data: 'You aren’t authorized to do that.',
				code: 'woopay_pay_for_order_not_authorized',
			},
		],
		[
			'a successful response',
			{
				success: true,
				data: 'text',
				code: 'woopay_pay_for_order_unavailable',
			},
		],
		[
			'a non-text payload',
			{
				success: false,
				data: { session: 'x' },
				code: 'woopay_pay_for_order_unavailable',
			},
		],
		[ 'no response', undefined ],
	] )( 'returns an empty string for %s', ( _, response ) => {
		expect( getSessionErrorMessage( response ) ).toBe( '' );
	} );
} );
