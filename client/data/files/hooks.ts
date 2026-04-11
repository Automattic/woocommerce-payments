/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import type { File, FileResponse } from './types';
import { STORE_NAME } from '../constants';

export const useFiles = ( id: string ): FileResponse =>
	useSelect(
		( select ) => {
			const selectors = select( STORE_NAME );

			const {
				getFile,
				getFileError,
				isResolving,
				hasFinishedResolution,
			} = selectors;

			const file: File = getFile( id );

			return {
				file: file || ( {} as File ),
				error: getFileError( id ),
				isLoading:
					isResolving( 'getFile', [ id ] ) ||
					! hasFinishedResolution( 'getFile', [ id ] ),
			};
		},
		[ id ]
	);
