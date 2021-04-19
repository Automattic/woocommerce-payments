/** @format */
/* eslint-disable camelcase */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	updateDepositsOverview,
	updateErrorForDepositsOverview,
	updateDeposit,
	updateDeposits,
	updateDepositsCount,
	updateErrorForDepositQuery,
} from '../actions';
import { getDepositsOverview, getDeposit, getDeposits } from '../resolvers';

const depositsResponse = {
	data: [
		{
			id: 'test_po_1',
			date: 1585617029000,
			type: 'deposit',
			amount: 3930,
			status: 'paid',
			bankAccount: 'STRIPE TEST BANK •••• 6789 (USD)',
		},
		{
			id: 'test_po_2',
			date: 1585617555000,
			type: 'deposit',
			amount: 4500,
			status: 'in_transit',
			bankAccount: 'STRIPE TEST BANK •••• 8599 (USD)',
		},
	],
	total_count: 2,
};

const errorResponse = { code: 'error' };

describe( 'getDepositsOverview resolver', () => {
	const successfulResponse = {
		last_deposit: depositsResponse.data[ 0 ],
		next_deposit: depositsResponse.data[ 1 ],
		balance: { pending: { amount: 5500 }, available: { amount: 0 } },
		deposits_schedule: { interval: 'daily' },
	};
	let generator = null;

	beforeEach( () => {
		generator = getDepositsOverview();
		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/deposits/overview' } )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with deposits overview', () => {
			expect( generator.next( successfulResponse ).value ).toEqual(
				updateDepositsOverview( successfulResponse )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch( 'core/notices' ).createErrorNotice(
					expect.any( String )
				)
			);
			expect( generator.next().value ).toEqual(
				updateErrorForDepositsOverview( errorResponse )
			);
		} );
	} );
} );

describe( 'getDeposit resolver', () => {
	let generator = null;

	beforeEach( () => {
		generator = getDeposit( 'test_dep_1' );
		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/deposits/test_dep_1' } )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with deposit data', () => {
			expect(
				generator.next( depositsResponse.data[ 0 ] ).value
			).toEqual( updateDeposit( depositsResponse.data[ 0 ] ) );
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch( 'core/notices' ).createErrorNotice(
					expect.any( String )
				)
			);
		} );
	} );
} );

describe( 'getDeposits resolver', () => {
	let generator = null;
	const query = { paged: 1, perPage: 25 };

	beforeEach( () => {
		generator = getDeposits( query );
		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/deposits?page=1&pagesize=25' } )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with deposits data', () => {
			expect( generator.next( depositsResponse ).value ).toEqual(
				updateDeposits( query, depositsResponse.data )
			);

			expect( generator.next().value ).toEqual(
				updateDepositsCount( depositsResponse.total_count )
			);

			depositsResponse.data.forEach( ( payout ) => {
				expect( generator.next().value ).toEqual(
					dispatch( 'wc/payments' ).finishResolution( 'getDeposit', [
						payout.id,
					] )
				);
			} );
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				dispatch( 'core/notices' ).createErrorNotice(
					expect.any( String )
				)
			);
			expect( generator.next().value ).toEqual(
				updateErrorForDepositQuery( query, null, errorResponse )
			);
		} );
	} );
} );
