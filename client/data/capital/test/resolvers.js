/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	updateActiveLoanSummary,
	updateErrorForActiveLoanSummary,
	updateLoans,
	updateErrorForLoans,
} from '../actions';

import { getActiveLoanSummary, getLoans } from '../resolvers';

const summaryResponse = {
	object: 'capital.financing_summary',
	details: {
		currency: 'usd',
		advance_amount: 100000,
		some_more: 'irrelevant_data',
	},
	status: 'active',
};

const errorResponse = { code: 'error' };

describe( 'getActiveLoanSummary resolver', () => {
	let generator = null;

	beforeEach( () => {
		generator = getActiveLoanSummary();
		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/capital/active_loan_summary' } )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with active loan summary', () => {
			expect( generator.next( summaryResponse ).value ).toEqual(
				updateActiveLoanSummary( summaryResponse )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				controls.dispatch(
					'core/notices',
					'createErrorNotice',
					expect.any( String )
				)
			);
			expect( generator.next().value ).toEqual(
				updateErrorForActiveLoanSummary( errorResponse )
			);
		} );
	} );
} );

describe( 'getLoans resolver', () => {
	let generator = null;

	beforeEach( () => {
		generator = getLoans();
		expect( generator.next().value ).toEqual(
			apiFetch( { path: '/wc/v3/payments/capital/loans' } )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with list of loans', () => {
			expect( generator.next( summaryResponse ).value ).toEqual(
				updateLoans( summaryResponse )
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error on error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				controls.dispatch(
					'core/notices',
					'createErrorNotice',
					expect.any( String )
				)
			);
			expect( generator.next().value ).toEqual(
				updateErrorForLoans( errorResponse )
			);
		} );
	} );
} );
