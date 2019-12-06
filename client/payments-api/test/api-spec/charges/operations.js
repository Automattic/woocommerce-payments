/** @format */

/**
 * Internal dependencies
 */
import {
	readCharge,
	chargeToResources,
} from '../../../api-spec/charges/operations';
import { NAMESPACE } from '../../../constants';

describe( 'Charges operations', () => {
	describe( 'readCharge()', () => {
		it( 'Returns a list with promises when charges are supplied', () => {
			const expectedUrl = ( id ) => `${ NAMESPACE }/payments/charges/${ id }`;
			const resourceList = [ 'ch_1', 'ch_2', 'ch_3' ];
			const mockPromises = resourceList.map( resource => Promise.resolve( { id: resource } ) );
			const mockFetch = jest.fn( ( args ) => mockPromises[ args.path.split( '_' ).pop() - 1 ] );
			const mockToResources = jest.fn( ( charge ) => charge.id );

			const promises = readCharge( resourceList, mockFetch, mockToResources );

			expect( mockFetch ).toHaveBeenCalledTimes( 3 );
			resourceList.forEach( resource => expect( mockFetch ).toHaveBeenCalledWith( { path: expectedUrl( resource ) } ) );

			return Promise.all( promises ).then( results => {
				expect( mockToResources ).toHaveBeenCalledTimes( 3 );
				expect( results ).toEqual( resourceList );
			} );
		} );

		it( 'Returns an empty list when wrong resource names are supplied', () => {
			const resourceList = [ 'tasxn_1', 'charge-list', 'somethingelse' ];
			const mockFetch = jest.fn();
			const response = readCharge( resourceList, mockFetch );
			expect( mockFetch ).not.toHaveBeenCalled();
			expect( response ).toStrictEqual( [] );
		} );
	} );

	describe( 'chargeToResources()', () => {
		it( 'should convert a charge to resource', () => {
			const charge = { id: 'ch_j329saja' };
			const expectedResource = {
				[ 'ch_j329saja' ]: { data: charge },
			};

			const resource = chargeToResources( charge );
			expect( resource ).toStrictEqual( expectedResource );
		} );
	} );
} );
