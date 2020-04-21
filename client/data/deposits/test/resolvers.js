/** @format */
/* eslint-disable camelcase */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import {
	updateDepositsOverview,
	updateErrorForDepositsOverview,
	updateDeposit,
	updateDeposits,
	updateErrorForDepositQuery,
} from '../actions';
import { getDepositsOverview, getDeposit, getDeposits } from '../resolvers';

const stripePayouts = [	{
	id: 'test_po_1',
	object: 'payout',
	amount: 3930,
	arrival_date: 1585617029,
	status: 'paid',
	destination: {
		bank_name: 'STRIPE TEST BANK',
		currency: 'usd',
		last4: '6789',
	},
}, {
	id: 'test_po_2',
	object: 'payout',
	amount: 4500,
	arrival_date: 1585617555,
	status: 'in_transit',
	destination: {
		bank_name: 'STRIPE TEST BANK',
		currency: 'usd',
		last4: '8599',
	},
} ];
const convertedStripePayouts = [ {
	id: 'test_po_1',
	date: 1585617029000,
	type: 'deposit',
	amount: 3930,
	status: 'paid',
	bankAccount: 'STRIPE TEST BANK •••• 6789 (USD)',
}, {
	id: 'test_po_2',
	date: 1585617555000,
	type: 'deposit',
	amount: 4500,
	status: 'in_transit',
	bankAccount: 'STRIPE TEST BANK •••• 8599 (USD)',
} ];
const errorResponse = { code: 'error' };

describe( 'getDepositsOverview resolver', () => {
	const successfulResponse = {
		last_deposit: convertedStripePayouts[ 0 ],
		next_deposit: convertedStripePayouts[ 1 ],
		balance: { pending: { amount: 5500 }, available: { amount: 0 } },
		deposits_schedule: { interval: 'daily' },
	};
	let generator = null;

	beforeEach( () => {
		generator = getDepositsOverview();
		expect( generator.next().value ).toEqual( apiFetch( { path: '/wc/v3/payments/deposits/overview' } ) );
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with deposits overview', () => {
			expect( generator.next( successfulResponse ).value ).toEqual( updateDepositsOverview( successfulResponse ) );
		} );

		test( 'should convert payout to deposits', () => {
			const successfulPayoutResponse = {
				...successfulResponse,
				...{ last_deposit: stripePayouts[ 0 ], next_deposit: stripePayouts[ 1 ] },
			};
			expect( generator.next( successfulPayoutResponse ).value ).toEqual( updateDepositsOverview( successfulResponse ) );
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch( 'core/notices', 'createErrorNotice', expect.any( String ) )
			);
			expect( generator.next().value ).toEqual( updateErrorForDepositsOverview( errorResponse ) );
		} );
	} );
} );

describe( 'getDeposit resolver', () => {
	let generator = null;

	beforeEach( () => {
		generator = getDeposit( 'test_dep_1' );
		expect( generator.next().value ).toEqual( apiFetch( { path: '/wc/v3/payments/deposits/test_dep_1' } ) );
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with deposit data', () => {
			expect( generator.next( convertedStripePayouts[ 0 ] ).value ).toEqual( updateDeposit( convertedStripePayouts[ 0 ] ) );
		} );

		test( 'should convert payout to deposit', () => {
			expect( generator.next( stripePayouts[ 0 ] ).value ).toEqual( updateDeposit( convertedStripePayouts[ 0 ] ) );
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch( 'core/notices', 'createErrorNotice', expect.any( String ) )
			);
		} );
	} );
} );

describe( 'getDeposits resolver', () => {
	let generator = null;
	const query = { paged: 1, perPage: 25 };

	beforeEach( () => {
		generator = getDeposits( query );
		expect( generator.next().value ).toEqual( apiFetch( { path: '/wc/v3/payments/deposits?page=1&pagesize=25' } ) );
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with deposits data', () => {
			expect( generator.next( { data: convertedStripePayouts } ).value )
				.toEqual( updateDeposits( query, convertedStripePayouts ) );
			convertedStripePayouts.forEach( payout => {
				expect( generator.next().value ).toEqual(
					dispatch( 'wc/payments', 'finishResolution', 'getDeposit', [ payout.id ] )
				);
			} );
		} );

		test( 'should convert payout to deposits', () => {
			expect( generator.next( { data: stripePayouts } ).value ).toEqual( updateDeposits( query, convertedStripePayouts ) );
			convertedStripePayouts.forEach( payout => {
				expect( generator.next().value ).toEqual(
					dispatch( 'wc/payments', 'finishResolution', 'getDeposit', [ payout.id ] )
				);
			} );
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch( 'core/notices', 'createErrorNotice', expect.any( String ) )
			);
			expect( generator.next().value ).toEqual( updateErrorForDepositQuery( query, null, errorResponse ) );
		} );
	} );
} );
