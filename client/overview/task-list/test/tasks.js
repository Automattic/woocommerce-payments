/** @format */

/**
 * Internal dependencies
 */
import { getTasks } from '../tasks';

describe( 'getTasks()', () => {
	it( 'should include business details when account will be restricted', () => {
		const actual = getTasks( {
			accountStatus: {
				status: 'restricted_soon',
				currentDeadline: 1620857083,
				pastDue: false,
				accountLink: 'http://example.com',
			},
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( { key: 'update-business-details' } ),
			] )
		);
	} );

	it( 'should include business details when account is restricted', () => {
		const actual = getTasks( {
			accountStatus: {
				status: 'restricted',
				currentDeadline: 1620857083,
				pastDue: true,
				accountLink: 'http://example.com',
			},
		} );

		expect( actual ).toEqual(
			expect.arrayContaining( [
				expect.objectContaining( { key: 'update-business-details' } ),
			] )
		);
	} );

	it( 'should not include business details when account is complete', () => {
		const actual = getTasks( {
			accountStatus: {
				status: 'complete',
				currentDeadline: 0,
				pastDue: false,
				accountLink: 'http://example.com',
			},
		} );

		expect( actual ).toEqual(
			expect.not.arrayContaining( [
				expect.objectContaining( { key: 'update-business-details' } ),
			] )
		);
	} );
} );
