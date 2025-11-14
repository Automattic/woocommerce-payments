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
	const path = `${ NAMESPACE }/payment-method-promotions`;

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
