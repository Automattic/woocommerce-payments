/** @format */

/**
 * Internal dependencies
 */
import { ApiError } from 'wcpay/types/errors';
import ACTION_TYPES from './action-types';

export interface File {
	/**
	 * Unique identifier for the file, expected to be prefixed by file_
	 */
	id: string;
	/**
	 * The purpose for file. eg 'dispute_evidence'.
	 */
	purpose: string;
	/**
	 * The filetype 'pdf' 'csv' 'jpg' etc.
	 */
	type: string;
	/**
	 * A filename for the file, suitable for saving to a filesystem.
	 */
	filename: string;
	/**
	 * The size in bytes.
	 */
	size: number;
	/**
	 * A user friendly title for the file.
	 */
	title: string | null;
}

export interface FileDownload {
	/**
	 * The file mime-type.
	 */
	content_type: string;
	/**
	 * The file content, base64 encoded.
	 */
	file_content: string;
}

export interface FileResponse {
	isLoading: boolean;
	file?: File;
	fileError?: ApiError;
}

export interface UpdateFilesAction {
	type: ACTION_TYPES.SET_FILE;
	id: string;
	data: File;
}

export interface UpdateErrorForFilesAction {
	type: ACTION_TYPES.SET_ERROR_FOR_FILES;
	id: string;
	error: ApiError;
}

export interface FilesState {
	[ key: string ]: {
		id: string;
		data?: File;
		error?: ApiError;
	};
}

export type FilesActions = UpdateFilesAction | UpdateErrorForFilesAction;
