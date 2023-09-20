/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';
import type {
	File,
	UpdateFilesAction,
	UpdateErrorForFilesAction,
} from './types';
import { ApiError } from 'wcpay/types/errors';

export function updateFiles( id: string, data: File ): UpdateFilesAction {
	return {
		type: ACTION_TYPES.SET_FILE,
		id,
		data,
	};
}

export function updateErrorForFiles(
	id: string,
	error: ApiError
): UpdateErrorForFilesAction {
	return {
		type: ACTION_TYPES.SET_ERROR_FOR_FILES,
		id,
		error,
	};
}
