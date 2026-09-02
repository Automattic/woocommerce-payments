/** @format */

/**
 * External dependencies
 */
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { refundCharge } from '../actions';
import { EARLY_FRAUD_WARNINGS_STORE_NAME } from '../../store-names';
import { Charge } from 'wcpay/types/charges';

const charge = {
	id: 'ch_flagged',
	amount: 2500,
	payment_intent: 'pi_1',
	order: { id: 42 },
} as Charge;

describe( 'refundCharge', () => {
	it( 'invalidates the active early fraud warnings so the Overview task stops showing a refunded payment', () => {
		const generator = refundCharge( charge, null );

		// Step past the refund request itself.
		generator.next();

		const dispatched = [];
		for ( let i = 0; i < 6; i++ ) {
			const { value, done } = generator.next();
			if ( done ) {
				break;
			}
			dispatched.push( value );
		}

		expect( dispatched ).toContainEqual(
			controls.dispatch(
				EARLY_FRAUD_WARNINGS_STORE_NAME,
				'invalidateResolutionForStoreSelector',
				'getActiveEarlyFraudWarnings'
			)
		);
	} );
} );
