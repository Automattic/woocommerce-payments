/** @format */

/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
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

describe( 'getAuthorizations resolver', () => {
	const query = { paged: '1', perPage: 25, orderBy: 'someKey' };
	let generator: Generator< unknown >;

	beforeEach( () => {
		generator = getAuthorizations( query );
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'should update state with authorizations data', () => {
		expect( generator.next().value ).toEqual(
			updateAuthorizations( query, [] )
		);
	} );
} );

describe( 'getAuthorization resolver', () => {
	let generator: Generator< unknown >;
	const mockPaymentIntentId = '42';
	const mockIsCaptured = false;
	const mockCaptureBy = dateI18n(
		'M j, Y / g:iA',
		moment.utc( new Date() ).add( '7', 'days' ).local().toISOString() // TODO: remove when getAuthorization switches to live data.
	);

	beforeEach( () => {
		generator = getAuthorization( mockPaymentIntentId, mockIsCaptured );
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'should update state with authorization data', () => {
		expect( generator.next().value ).toEqual(
			updateAuthorization( {
				payment_intent_id: mockPaymentIntentId,
				captured: mockIsCaptured,
				capture_by: mockCaptureBy,
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
