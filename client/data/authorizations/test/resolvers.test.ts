/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import {
	Authorization,
	AuthorizationsSummary,
} from 'wcpay/types/authorizations';
import {
	updateAuthorization,
	updateAuthorizations,
	updateAuthorizationsSummary,
} from '../actions';
import {
	getAuthorization,
	getAuthorizations,
	getAuthorizationsSummary,
} from '../resolvers';
import { NAMESPACE } from '../../constants';

describe( 'getAuthorizations resolver', () => {
	const query = { paged: '1', perPage: 25, orderBy: 'someKey' };
	let generator: Generator< unknown >;

	beforeEach( () => {
		generator = getAuthorizations( query );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/authorizations?page=1&pagesize=25&sort=created&direction=desc`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'should update state with authorizations data', () => {
		expect( generator.next( { data: [] } ).value ).toEqual(
			updateAuthorizations( query, { data: [] } )
		);
	} );
} );

describe( 'getAuthorization resolver', () => {
	let generator: Generator< unknown >;
	const mockPaymentIntentId = '42';
	const mockIsCaptured = false;

	beforeEach( () => {
		generator = getAuthorization( mockPaymentIntentId );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/authorizations/42`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'should update state with authorization data', () => {
		expect(
			generator.next( {
				payment_intent_id: mockPaymentIntentId,
				is_captured: mockIsCaptured,
			} ).value
		).toEqual(
			updateAuthorization( {
				payment_intent_id: mockPaymentIntentId,
				captured: mockIsCaptured,
			} as Authorization )
		);
	} );
} );

describe( 'getAuthorizationsSummary resolver', () => {
	const query = { paged: '1', perPage: 25, orderBy: 'someKey' };
	let generator: Generator< unknown >;

	beforeEach( () => {
		generator = getAuthorizationsSummary( query );
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'should update state with authorizations data', () => {
		expect( generator.next().value ).toEqual(
			updateAuthorizationsSummary( query, {} as AuthorizationsSummary )
		);
	} );
} );
