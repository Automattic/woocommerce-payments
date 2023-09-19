/**
 * Internal dependencies
 */
import reducer from '../reducer';
import { updateCharge, updateErrorForCharge } from '../actions';
import { chargeId, chargeMock } from 'wcpay/data/payment-intents/test/hooks';

describe( 'Charges reducer tests', () => {
	test( 'default state equals expected', () => {
		const defaultState = reducer( {}, { type: 'foo' } as any );

		expect( defaultState ).toEqual( {} );
	} );

	test( 'sets the `data` field', () => {
		const state: any = reducer(
			{},
			updateCharge( chargeId, chargeMock ) as any
		);

		expect( state[ chargeId ].data ).toEqual( chargeMock );
	} );

	test( 'sets the `error` field', () => {
		const error = { code: 'error' };

		const state: any = reducer(
			{},
			updateErrorForCharge( chargeId, undefined, error )
		);

		expect( state[ chargeId as any ].error ).toEqual( error );
	} );
} );
