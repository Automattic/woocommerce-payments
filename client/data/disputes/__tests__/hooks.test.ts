/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useDisputes } from '../hooks';

jest.mock( '@wordpress/data' );

describe( 'Dispute data hooks', () => {
	const getDisputes = jest.fn().mockReturnValue( [] );
	const isResolving = jest.fn().mockReturnValue( false );
	const hasFinishedResolution = jest.fn().mockReturnValue( false );

	beforeEach( () => {
		jest.clearAllMocks();
		( useSelect as jest.Mock ).mockImplementation( ( callback ) =>
			callback( () => ( {
				getDisputes,
				isResolving,
				hasFinishedResolution,
			} ) )
		);
	} );

	it( 'does not resolve dispute rows when loading is disabled', () => {
		const result = useDisputes(
			{ filter: 'awaiting_response', per_page: '1' },
			false
		);

		expect( result ).toEqual( { disputes: [], isLoading: false } );
		expect( getDisputes ).not.toHaveBeenCalled();
	} );

	it( 'uses the existing loading behavior when shouldLoad is omitted', () => {
		const result = useDisputes( {
			filter: 'awaiting_response',
			per_page: '1',
		} );

		expect( result ).toEqual( { disputes: [], isLoading: false } );
		expect( getDisputes ).toHaveBeenCalledWith(
			expect.objectContaining( {
				filter: 'awaiting_response',
				perPage: '1',
			} )
		);
		expect( isResolving ).toHaveBeenCalledWith(
			'getDisputes',
			expect.any( Array )
		);
		expect( hasFinishedResolution ).not.toHaveBeenCalled();
	} );

	it( 'stays loading during the first explicitly enabled resolution', () => {
		const result = useDisputes(
			{ filter: 'awaiting_response', per_page: '1' },
			true
		);

		expect( result ).toEqual( { disputes: [], isLoading: true } );
		expect( getDisputes ).toHaveBeenCalledWith(
			expect.objectContaining( {
				filter: 'awaiting_response',
				perPage: '1',
			} )
		);
	} );
} );
