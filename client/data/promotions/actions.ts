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
import ACTION_TYPES from './action-types';
import {
	PromotionsData,
	UpdatePromotionsAction,
	ErrorPromotionsAction,
} from './types';
import { ApiError } from '../../types/errors';
import { NAMESPACE } from '../constants';

export function updatePromotions(
	data: PromotionsData
): UpdatePromotionsAction {
	return {
		type: ACTION_TYPES.SET_PROMOTIONS,
		data,
	};
}

export function updateErrorForPromotions(
	error: ApiError
): ErrorPromotionsAction {
	return {
		type: ACTION_TYPES.SET_ERROR_FOR_PROMOTIONS,
		error,
	};
}

/**
 * Activate a promotion.
 *
 * @param {string} identifier The promotion identifier.
 * @param {boolean} acceptTerms Whether to accept the promotion terms.
 */
export function* activatePromotion(
	identifier: string,
	acceptTerms = true
): unknown {
	const path = `${ NAMESPACE }/promotions/${ identifier }/activate`;

	try {
		yield apiFetch( {
			path,
			method: 'POST',
			data: { accept_terms: acceptTerms },
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
			'getPromotions',
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
		yield updateErrorForPromotions( e as ApiError );
	}
}

/**
 * Dismiss a promotion.
 *
 * @param {string} identifier The promotion identifier.
 */
export function* dismissPromotion( identifier: string ): unknown {
	const path = `${ NAMESPACE }/promotions/${ identifier }/dismiss`;

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
			'getPromotions',
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
		yield updateErrorForPromotions( e as ApiError );
	}
}
