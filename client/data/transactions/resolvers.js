/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import {
	updateTransactions,
	updateErrorForTransactions,
	updateTransactionsSummary,
	updateErrorForTransactionsSummary,
	updateFraudOutcomeTransactions,
	updateErrorForFraudOutcomeTransactions,
	updateFraudOutcomeTransactionsSummary,
	updateErrorForFraudOutcomeTransactionsSummary,
} from './actions';
import { formatDateValue } from 'utils';

function getUserTimeZone() {
	return moment( new Date() ).format( 'Z' );
}

export const formatQueryFilters = ( query ) => ( {
	user_email: query.userEmail,
	match: query.match,
	date_before: formatDateValue( query.dateBefore, true ),
	date_after: formatDateValue( query.dateAfter ),
	date_between: query.dateBetween && [
		formatDateValue( query.dateBetween[ 0 ] ),
		formatDateValue( query.dateBetween[ 1 ], true ),
	],
	type_is: query.typeIs,
	type_is_not: query.typeIsNot,
	source_device_is: query.sourceDeviceIs,
	source_device_is_not: query.sourceDeviceIsNot,
	channel_is: query.channelIs,
	channel_is_not: query.channelIsNot,
	customer_country_is: query.customerCountryIs,
	customer_country_is_not: query.customerCountryIsNot,
	risk_level_is: query.riskLevelIs,
	risk_level_is_not: query.riskLevelIsNot,
	store_currency_is: query.storeCurrencyIs,
	loan_id_is: query.loanIdIs,
	deposit_id: query.depositId,
	customer_currency_is: query.customerCurrencyIs,
	customer_currency_is_not: query.customerCurrencyIsNot,
	search: query.search,
	user_timezone: getUserTimeZone(),
	locale: query.locale,
} );

/**
 * Retrieves a series of transactions from the transactions list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getTransactions( query ) {
	const path = addQueryArgs( `${ NAMESPACE }/transactions`, {
		page: query.paged,
		pagesize: query.perPage,
		sort: query.orderby,
		direction: query.order,
		...formatQueryFilters( query ),
	} );

	try {
		const results = yield apiFetch( { path } );
		yield updateTransactions( query, results.data || [] );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving transactions.', 'woocommerce-payments' )
		);
		yield updateErrorForTransactions( query, null, e );
	}
}

export function getTransactionsCSV( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions/download`,
		formatQueryFilters( query )
	);

	return path;
}

/**
 * Retrieves the transactions summary from the summary API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getTransactionsSummary( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions/summary`,
		formatQueryFilters( query )
	);

	try {
		const summary = yield apiFetch( { path } );
		yield updateTransactionsSummary( query, summary );
	} catch ( e ) {
		yield updateErrorForTransactionsSummary( query, null, e );
	}
}

/**
 * Retrieves the blocked transactions.
 *
 * @param { string } status Fraud outcome status to be filtered.
 * @param { string } query Data on which to parameterize the selection.
 */
export function* getFraudOutcomeTransactions( status, query ) {
	const path = addQueryArgs( `${ NAMESPACE }/transactions/fraud-outcomes`, {
		status,
		page: query.paged,
		sort: query.orderby,
		pagesize: query.perPage,
		direction: query.order,
		additional_status: query.additionalStatus,
		...formatQueryFilters( query ),
	} );

	try {
		const results = yield apiFetch( { path } );
		yield updateFraudOutcomeTransactions(
			status,
			query,
			results.data || []
		);
	} catch ( e ) {
		if ( e.code === 'wcpay_fraud_outcome_not_found' ) {
			yield updateFraudOutcomeTransactions( status, query, [] );
			return;
		}

		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving transactions.', 'woocommerce-payments' )
		);
		yield updateErrorForFraudOutcomeTransactions( status, query, e );
	}
}

/**
 * Retrieves the on review transactions.
 *
 * @param { string } status Fraud outcome status to be filtered.
 * @param { string } query Data on which to parameterize the selection.
 */
export function* getFraudOutcomeTransactionsSummary( status, query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions/fraud-outcomes/summary`,
		{
			status,
			additional_status: query.additionalStatus,
		}
	);

	const summaryFallback = {
		count: 0,
		total: 0,
	};

	try {
		const result = yield apiFetch( { path } );
		yield updateFraudOutcomeTransactionsSummary(
			status,
			query,
			result || summaryFallback
		);
	} catch ( e ) {
		if ( e.code === 'wcpay_fraud_outcome_not_found' ) {
			yield updateFraudOutcomeTransactionsSummary(
				status,
				query,
				summaryFallback
			);
			return;
		}

		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving on review transactions.',
				'woocommerce-payments'
			)
		);
		yield updateErrorForFraudOutcomeTransactionsSummary( status, query, e );
	}
}

export function getFraudOutcomeTransactionsExport( status, query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/transactions/fraud-outcomes/download`,
		{
			status,
			sort: query.orderby,
			direction: query.order,
			additional_status: query.additionalStatus,
			...formatQueryFilters( query ),
		}
	);

	return path;
}
