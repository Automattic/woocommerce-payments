/** @format */
/**
 * External dependencies
 */
import { dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import ACTION_TYPES from './action-types';
import { STORE_NAME, NAMESPACE } from '../constants';
import {
	Country,
	UpdateRequiredVerificationInfoAction,
	UpdateBusinessTypesAction,
	RequiredVerificationInfoParams,
} from './types';

export function updateBusinessTypes(
	data: Country[]
): UpdateBusinessTypesAction {
	return {
		type: ACTION_TYPES.SET_BUSINESS_TYPES,
		data,
	};
}

export function updateRequiredVerificationInfo(
	data: string[]
): UpdateRequiredVerificationInfoAction {
	return {
		type: ACTION_TYPES.SET_REQUIRED_VERIFICATION_INFO,
		data,
	};
}

export function* getRequiredVerificationInfo(
	query: RequiredVerificationInfoParams
): unknown {
	try {
		yield dispatch( STORE_NAME ).startResolution(
			'getRequiredVerificationInfo',
			[]
		);

		const path = addQueryArgs(
			`${ NAMESPACE }/onboarding/required_verification_information`,
			query
		);

		const result = yield apiFetch( { path } );

		yield updateRequiredVerificationInfo( result.data );
	} catch ( e ) {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__(
				'Error retrieving required verification information',
				'woocommerce-payments'
			)
		);
	} finally {
		yield dispatch( STORE_NAME ).finishResolution(
			'getRequiredVerificationInfo',
			[]
		);
	}
}
