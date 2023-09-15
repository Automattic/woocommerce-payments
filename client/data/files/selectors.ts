/** @format */

/**
 * Internal dependencies
 */
import { State } from 'wcpay/data/types';
import { File } from './types';
import { ApiError } from 'wcpay/types/errors';

export const getFile = ( { files }: State, id: string ): File => {
	const file = files?.[ id ];

	return file?.data || ( {} as File );
};

export const getFileError = ( { files }: State, id: string ): ApiError => {
	const file = files?.[ id ];

	return file?.error || ( {} as ApiError );
};
