/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updatePaymentActivity } from './actions';
import type { PaymentActivityData, PaymentActivityQuery } from './types';

/**
 * Retrieves payment activity data from the reporting API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getPaymentActivityData(
	query: PaymentActivityQuery
): Generator< unknown > {
	const path = addQueryArgs(
		`${ NAMESPACE }/reporting/payment_activity`,
		query
	);

	try {
		const results = yield apiFetch( { path } );

		yield updatePaymentActivity( results as PaymentActivityData );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving payment activity data.',
				'woocommerce-payments'
			)
		);
	}
}
