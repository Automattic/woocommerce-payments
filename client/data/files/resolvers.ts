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
import { updateFiles, updateErrorForFiles } from './actions';
import { File } from './types';
import { ApiError } from 'wcpay/types/errors';

/**
 * Retrieve a single dispute from the disputes API.
 *
 * @param {string} id Identifier for specified dispute to retrieve.
 */
export function* getFile( id: string ): Generator< unknown > {
	try {
		const result = yield apiFetch( {
			path: `${ NAMESPACE }/file/detail/${ id }`,
		} );
		yield updateFiles( id, result as File );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving file.', 'woocommerce-payments' )
		);
		yield updateErrorForFiles( id, e as ApiError );
	}
}
