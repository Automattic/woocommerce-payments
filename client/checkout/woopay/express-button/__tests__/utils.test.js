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
	test( "returns the store's message from a refused pay-for-order request", () => {
		expect(
			getSessionErrorMessage( 'pay_for_order', {
				success: false,
				data: STORE_MESSAGE,
			} )
		).toBe( 'WooPay couldn’t load this order.' );
	} );

	test.each( [
		[
			'a refusal outside the pay-for-order page',
			'checkout',
			{ success: false, data: 'You aren’t authorized to do that.' },
		],
		[
			'a successful response',
			'pay_for_order',
			{ success: true, data: 'text' },
		],
		[
			'a non-text payload',
			'pay_for_order',
			{ success: false, data: { code: 'x' } },
		],
		[ 'no response', 'pay_for_order', undefined ],
	] )( 'returns an empty string for %s', ( _, context, response ) => {
		expect( getSessionErrorMessage( context, response ) ).toBe( '' );
	} );
} );
