/** @format */

/**
 * External dependencies
 */
import { dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { updateSettings } from './actions';

/**
 * Retrieve settings from the site's REST API.
 */
export function* getSettings() {
	const path = `${ NAMESPACE }/settings`;

	try {
		const result = yield apiFetch( { path } );
		yield updateSettings( result );

		const surveyResult = yield apiFetch( {
			path: `${ NAMESPACE }/survey`,
			method: 'POST',
			data: {
				site_id: 999999999,
				survey_id: 'calypso-cancel-auto-renewal',
				survey_responses: {
					response: 'not-sure',
				},
			},
		} );
		console.log( '### surveyResult', { surveyResult } );
	} catch ( e ) {
		console.log( '#### e', { e } );
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error retrieving settings.', 'woocommerce-payments' )
		);
	}
}

/**
 * Retrieve settings from the site's REST API.
 */
export function* submitSurvey() {
	try {
		const surveyResult = yield apiFetch( {
			path: `${ NAMESPACE }/survey`,
		} );
		console.log( '### surveyResult', { surveyResult } );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error creating survey.', 'woocommerce-payments' )
		);
	}
}
