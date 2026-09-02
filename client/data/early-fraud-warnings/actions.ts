/** @format */

/**
 * Internal dependencies
 */
import { ACTION_TYPES } from './action-types';
import { ApiError } from '../../types/errors';
import {
	ActiveEarlyFraudWarning,
	ErrorActiveEarlyFraudWarningsAction,
	UpdateActiveEarlyFraudWarningsAction,
} from './types';

export const updateActiveEarlyFraudWarnings = (
	data: ActiveEarlyFraudWarning[]
): UpdateActiveEarlyFraudWarningsAction => ( {
	type: ACTION_TYPES.SET_ACTIVE_EARLY_FRAUD_WARNINGS,
	data,
} );

export const updateErrorForActiveEarlyFraudWarnings = (
	error: ApiError
): ErrorActiveEarlyFraudWarningsAction => ( {
	type: ACTION_TYPES.SET_ERROR_FOR_ACTIVE_EARLY_FRAUD_WARNINGS,
	error,
} );
