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
import { STORE_NAME } from '../../constants';

jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/data-controls' );
jest.mock( '@woocommerce/navigation' );

describe( 'acceptDispute action', () => {
	const mockDispute = {
		id: 'dp_mock1',
		reason: 'product_unacceptable',
		status: 'lost',
	};

	beforeEach( () => {
		const noticesDispatch = {
			createSuccessNotice: jest.fn(),
			createErrorNotice: jest.fn(),
		};

		apiFetch.mockImplementation( () => {} );
		dispatch.mockImplementation( ( storeName ) => {
			if ( 'core/notices' === storeName ) {
				return noticesDispatch;
			} else if ( STORE_NAME === storeName ) {
				return {
					startResolution: jest.fn(),
					finishResolution: jest.fn(),
				};
			}
			return {};
		} );
		getHistory.mockImplementation( () => {
			return { push: () => {} };
		} );
	} );

	test( 'should close dispute and update state with dispute data', () => {
		apiFetch.mockReturnValue( mockDispute );

		const generator = acceptDispute( 'dp_mock1' );

		generator.next(); // startResolution

		const yieldedFromFetch = generator.next(); // apiFetch
		expect( yieldedFromFetch.value ).toBe( mockDispute );

		const yieldedFromUpdateDispute = generator.next( mockDispute ); // updateDispute
		expect( yieldedFromUpdateDispute.value ).toStrictEqual(
			updateDispute( mockDispute )
		);

		generator.next(); // finishResolution
		generator.next(); // createSuccessNotice

		expect( getHistory ).toHaveBeenCalledTimes( 1 );
		expect( generator.next().done ).toBe( true );

		expect(
			dispatch( 'core/notices' ).createSuccessNotice
		).toHaveBeenCalledWith( 'You have accepted the dispute.' );
	} );

	test( 'should show notice on error', () => {
		// Make fetch throw an error.
		apiFetch.mockImplementation( () => {
			throw new Error();
		} );

		const generator = acceptDispute( 'dp_mock1' );

		// eslint-disable-next-line no-unused-expressions
		[ ...generator ];

		expect(
			dispatch( 'core/notices' ).createErrorNotice
		).toHaveBeenCalledWith(
			'There has been an error accepting the dispute. Please try again later.'
		);
	} );
} );
