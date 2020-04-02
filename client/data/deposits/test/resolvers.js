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
} from '../actions';
import { getDepositsOverview } from '../resolvers';

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
