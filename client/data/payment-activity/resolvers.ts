/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import type { Query } from '@woocommerce/navigation';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updatePaymentsActivity } from './actions';
import { PaymentActivityData, QueryDate } from './types';

/**
 * Retrieves payment activity data from the reporting API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getPaymentActivityData(
	query: QueryDate
): Generator< unknown > {
	const path = addQueryArgs(
		`${ NAMESPACE }/reporting/payment_activity`,
		query
	);

	try {
		const results = yield apiFetch( { path } );

		yield updatePaymentsActivity( results as PaymentActivityData );
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
