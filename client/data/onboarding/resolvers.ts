/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { dispatch as dispatcher } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE, STORE_NAME } from '../constants';
import { updateBusinessTypes, updateRequiredVerificationInfo } from './actions';
import { Country, RequiredVerificationInfoParams } from './types';
import { addQueryArgs } from '@wordpress/url';

/**
 * Retrieve business types from the site's REST API.
 */
export function* getBusinessTypes(): unknown {
	const path = `${ NAMESPACE }/onboarding/business_types`;

	try {
		const result = yield apiFetch( { path } );
		yield updateBusinessTypes( result.data as Country[] );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving business types', 'woocommerce-payments' )
		);
	}
}

export function* getRequiredVerificationInfo(
	query: RequiredVerificationInfoParams
): unknown {
	const path = addQueryArgs(
		`${ NAMESPACE }/onboarding/required_verification_information`,
		query
	);

	yield dispatcher( STORE_NAME ).invalidateResolutionForStoreSelector(
		'updateRequiredVerificationInfo'
	);

	try {
		const result = yield apiFetch( { path } );

		yield updateRequiredVerificationInfo( result.data );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving required verification information',
				'woocommerce-payments'
			)
		);
	}
}
