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
import { updatePromotions, updateErrorForPromotions } from './actions';
import { PromotionsData } from './types';
import { ApiError } from '../../types/errors';

/**
 * Retrieve promotions data.
 */
export function* getPromotions(): unknown {
	const path = `${ NAMESPACE }/promotions`;

	try {
		const result = yield apiFetch( { path } );
		yield updatePromotions( result as PromotionsData );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving promotions. Please try again later.',
				'woocommerce-payments'
			)
		);
		yield updateErrorForPromotions( e as ApiError );
	}
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

		// Refresh promotions list after activation.
		yield getPromotions();
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

		// Refresh promotions list after dismissal.
		yield getPromotions();
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
