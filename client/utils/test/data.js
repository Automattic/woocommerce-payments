/**
 * Internal dependencies
 */
import { getResourceId } from '../data';

describe( 'General data store utility functions', () => {
	test( 'getResourceId returns the right string', () => {
		const query = { paged: '13', perPage: '100' };
		expect( getResourceId( query ) ).toBe(
			'{"paged":"13","perPage":"100"}'
		);
	} );
} );
