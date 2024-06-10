/**
 * Internal dependencies
 */
import receivePaymentActivity from '../reducer';
import types from '../action-types';
import { PaymentActivityData, PaymentActivityAction } from '../types';
import { getResourceId } from 'utils/data';

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
		const query = {
			currency: 'jpy',
			date_start: '2024-01-01',
			date_end: '2024-01-31',
			timezone: 'UTC',
		};
		const action: PaymentActivityAction = {
			type: types.SET_PAYMENT_ACTIVITY_DATA,
			data: mockPaymentActivityData,
			query,
		};

		const newState = receivePaymentActivity( initialState, action );
		const stateIndex = getResourceId( query );

		expect( newState ).toEqual( {
			[ stateIndex ]: action.data,
		} );
	} );
} );
