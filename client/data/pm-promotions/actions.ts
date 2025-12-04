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
import { ACTION_TYPES } from './action-types';
import {
	PmPromotionsData,
	UpdatePmPromotionsAction,
	ErrorPmPromotionsAction,
} from './types';
import { ApiError } from '../../types/errors';
import { NAMESPACE } from '../constants';

/**
 * Type guard to check if an error is an ApiError.
 */
function isApiError( error: unknown ): error is ApiError {
	return (
		typeof error === 'object' &&
		error !== null &&
		'code' in error &&
		typeof ( error as ApiError ).code === 'string'
	);
}

/**
 * Normalizes an unknown error to an ApiError.
 */
function normalizeError( error: unknown ): ApiError {
	if ( isApiError( error ) ) {
		return error;
	}
	return {
		code: 'unknown_error',
	};
}

export function updatePmPromotions(
	data: PmPromotionsData
): UpdatePmPromotionsAction {
	return {
		type: ACTION_TYPES.SET_PM_PROMOTIONS,
		data,
	};
}

export function updateErrorForPmPromotions(
	error: ApiError
): ErrorPmPromotionsAction {
	return {
		type: ACTION_TYPES.SET_ERROR_FOR_PM_PROMOTIONS,
		error,
	};
}

/**
 * Activate a PM promotion.
 *
 * @param {string} identifier The promotion identifier.
 */
export function* activatePmPromotion( identifier: string ): unknown {
	const path = `${ NAMESPACE }/pm-promotions/${ identifier }/activate`;

	try {
		yield apiFetch( {
			path,
			method: 'POST',
		} );

		yield controls.dispatch(
			'core/notices',
			'createSuccessNotice',
			__( 'Promotion activated successfully!', 'woocommerce-payments' )
		);

		// Refetch promotions to update the list.
		yield controls.dispatch(
			'wc/payments',
			'invalidateResolution',
			'getPmPromotions',
			[]
		);
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error activating promotion. Please try again.',
				'woocommerce-payments'
			)
		);
		yield controls.dispatch(
			'wc/payments',
			'updateErrorForPmPromotions',
			normalizeError( e )
		);
	}
}

/**
 * Dismiss a PM promotion.
 *
 * @param {string} id The promotion unique identifier.
 */
export function* dismissPmPromotion( id: string ): unknown {
	const path = `${ NAMESPACE }/pm-promotions/${ id }/dismiss`;

	try {
		yield apiFetch( {
			path,
			method: 'POST',
		} );

		yield controls.dispatch(
			'core/notices',
			'createSuccessNotice',
			__( 'Promotion dismissed.', 'woocommerce-payments' )
		);

		// Refetch promotions to update the list.
		yield controls.dispatch(
			'wc/payments',
			'invalidateResolution',
			'getPmPromotions',
			[]
		);
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error dismissing promotion. Please try again.',
				'woocommerce-payments'
			)
		);
		yield controls.dispatch(
			'wc/payments',
			'updateErrorForPmPromotions',
			normalizeError( e )
		);
	}
}
