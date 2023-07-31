/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateCharge, updateErrorForCharge } from './actions';

export function* getCharge( id ) {
	try {
		const results = yield apiFetch( {
			path: `${ NAMESPACE }/charges/${ id }`,
		} );
		yield updateCharge( id, results );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving transaction.', 'woocommerce-payments' )
		);
		yield updateErrorForCharge( id, null, e );
	}
}

export function* getChargeFromOrder( id ) {
	try {
		const results = yield apiFetch( {
			path: `${ NAMESPACE }/charges/order/${ id }`,
		} );
		yield updateCharge( id, results );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving transaction.', 'woocommerce-payments' )
		);
		yield updateErrorForCharge( id, null, e );
	}
}
