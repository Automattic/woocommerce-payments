/** @format */

/**
 * External dependencies
 */
import { dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { getHistory } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { acceptDispute, updateDispute } from '../actions';

jest.mock( '@woocommerce/navigation', () => ( {
	getHistory: jest.fn( () => ( { push: () => {} } ) ),
} ) );

describe( 'acceptDispute action', () => {
	const mockDispute = {
		id: 'dp_mock1',
		reason: 'product_unacceptable',
		status: 'lost',
	};

	test( 'should close dispute and update state with dispute data', () => {
		const generator = acceptDispute( 'dp_mock1' );

		expect( generator.next().value ).toEqual(
			dispatch( 'wc/payments', 'startResolution', 'getDispute', [
				'dp_mock1',
			] )
		);
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: '/wc/v3/payments/disputes/dp_mock1/close',
				method: 'post',
			} )
		);
		expect( generator.next( mockDispute ).value ).toEqual(
			updateDispute( mockDispute )
		);
		expect( generator.next().value ).toEqual(
			dispatch( 'wc/payments', 'finishResolution', 'getDispute', [
				'dp_mock1',
			] )
		);

		const noticeAction = generator.next().value;
		expect( getHistory.mock.calls.length ).toEqual( 1 );
		expect( noticeAction ).toEqual(
			dispatch(
				'core/notices',
				'createSuccessNotice',
				expect.any( String )
			)
		);
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'should show notice on error', () => {
		const generator = acceptDispute( 'dp_mock1' );

		generator.next();
		expect( generator.throw( { code: 'error' } ).value ).toEqual(
			dispatch(
				'core/notices',
				'createErrorNotice',
				expect.any( String )
			)
		);
	} );
} );
