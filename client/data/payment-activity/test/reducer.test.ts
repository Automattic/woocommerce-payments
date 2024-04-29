/**
 * Internal dependencies
 */
import receivePaymentActivity from '../reducer';
import types from '../action-types';
import { PaymentActivityData } from '../types';

describe( 'receivePaymentActivity', () => {
	const mockPaymentActivityData: PaymentActivityData = {
		total_payment_volume: 2500,
		charges: 3000,
		fees: 300,
		disputes: 315,
		refunds: 200,
		currency: 'jpy',
		timezone: 'UTC',
		date_start: '2024-01-01',
		date_end: '2024-01-31',
		interval: 'daily',
	};

	test( 'should set payment activity data correctly', () => {
		const initialState = {};
		const action = {
			type: types.SET_PAYMENT_ACTIVITY_DATA,
			data: mockPaymentActivityData,
		};

		const newState = receivePaymentActivity( initialState, action );

		expect( newState ).toEqual( {
			paymentActivityData: action.data,
		} );
	} );
} );
