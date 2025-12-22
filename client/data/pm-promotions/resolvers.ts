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
import { PmPromotion, PmPromotionsData } from './types';
import { ApiError } from '../../types/errors';

/**
 * Type guard to check if an object is a valid PmPromotion.
 */
function isPmPromotion( value: unknown ): value is PmPromotion {
	if ( typeof value !== 'object' || value === null ) {
		return false;
	}
	const obj = value as Record< string, unknown >;
	return (
		typeof obj.id === 'string' &&
		typeof obj.promo_id === 'string' &&
		typeof obj.payment_method === 'string' &&
		typeof obj.payment_method_title === 'string' &&
		typeof obj.type === 'string' &&
		( obj.type === 'spotlight' || obj.type === 'badge' ) &&
		typeof obj.title === 'string' &&
		typeof obj.description === 'string' &&
		typeof obj.cta_label === 'string' &&
		typeof obj.tc_url === 'string' &&
		typeof obj.tc_label === 'string'
	);
}

/**
 * Type guard to check if a value is valid PmPromotionsData.
 */
function isPmPromotionsData( value: unknown ): value is PmPromotionsData {
	return Array.isArray( value ) && value.every( isPmPromotion );
}

/**
 * Type guard to check if an error is an ApiError.
 */
function isApiError( error: unknown ): error is ApiError {
	return typeof error === 'object' && error !== null && 'code' in error;
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

/**
 * Retrieve PM promotions data.
 */
export function* getPmPromotions(): unknown {
	const path = `${ NAMESPACE }/pm-promotions`;

	try {
		const result = yield apiFetch( { path } );

		if ( ! isPmPromotionsData( result ) ) {
			throw new Error( 'Invalid promotions data received from API' );
		}

		yield controls.dispatch( 'wc/payments', 'updatePmPromotions', result );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving promotions. Please try again later.',
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
