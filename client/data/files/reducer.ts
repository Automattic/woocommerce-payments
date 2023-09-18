/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import {
	UpdateErrorForFilesAction,
	FilesState,
	FilesActions,
	UpdateFilesAction,
} from './types';

const defaultState = {};

const receiveFiles = (
	state: FilesState = defaultState,
	action: FilesActions
): FilesState => {
	const { type, id } = action;

	switch ( type ) {
		case TYPES.SET_FILE:
			return {
				...state,
				[ id ]: {
					...state[ id ],
					data: ( action as UpdateFilesAction ).data,
				},
			};
		case TYPES.SET_ERROR_FOR_FILES:
			return {
				...state,
				[ id ]: {
					...state[ id ],
					error: ( action as UpdateErrorForFilesAction ).error,
				},
			};
		default:
			return state;
	}
};

export default receiveFiles;
