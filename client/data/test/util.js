
/**
 * Internal dependencies
 */
import { getResourceId } from '../util';

describe( 'General data store utility functions', () => {
	test( 'getResourceId returns the right string', () => {
		const query = { paged: '13', perPage: '100' };
		expect( getResourceId( 'this-here-prefix', query ) ).toBe(
			'this-here-prefix:{"paged":"13","perPage":"100"}'
		);
	} );
} );
