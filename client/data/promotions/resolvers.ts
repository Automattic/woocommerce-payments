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
import { Promotion, PromotionVariation, PromotionsData } from './types';
import { ApiError } from '../../types/errors';

/**
 * Type guard to check if an object is a valid PromotionVariation.
 */
function isPromotionVariation( value: unknown ): value is PromotionVariation {
	if ( typeof value !== 'object' || value === null ) {
		return false;
	}
	const obj = value as Record< string, unknown >;
	return (
		typeof obj.id === 'string' &&
		typeof obj.type === 'string' &&
		typeof obj.heading === 'string' &&
		typeof obj.description === 'string' &&
		typeof obj.cta_label === 'string' &&
		typeof obj.cta_url === 'string'
	);
}

/**
 * Type guard to check if an object is a valid Promotion.
 */
function isPromotion( value: unknown ): value is Promotion {
	if ( typeof value !== 'object' || value === null ) {
		return false;
	}
	const obj = value as Record< string, unknown >;
	return (
		typeof obj.promo_id === 'string' &&
		typeof obj.discount_rate === 'string' &&
		typeof obj.duration_days === 'number' &&
		Array.isArray( obj.variations ) &&
		obj.variations.every( isPromotionVariation )
	);
}

/**
 * Type guard to check if a value is valid PromotionsData.
 */
function isPromotionsData( value: unknown ): value is PromotionsData {
	return Array.isArray( value ) && value.every( isPromotion );
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
 * Retrieve promotions data.
 */
export function* getPromotions(): unknown {
	const path = `${ NAMESPACE }/payment-method-promotions`;

	try {
		const result = yield apiFetch( { path } );

		if ( ! isPromotionsData( result ) ) {
			throw new Error( 'Invalid promotions data received from API' );
		}

		yield controls.dispatch( 'wc/payments', 'updatePromotions', result );
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
			'updateErrorForPromotions',
			normalizeError( e )
		);
	}
}
