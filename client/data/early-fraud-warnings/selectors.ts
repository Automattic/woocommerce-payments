/** @format */

/**
 * Internal dependencies
 */
import { ApiError } from '../../types/errors';
import { ActiveEarlyFraudWarning, EarlyFraudWarningsState } from './types';

interface State {
	earlyFraudWarnings: EarlyFraudWarningsState;
}

export const getActiveEarlyFraudWarnings = (
	state: State
): ActiveEarlyFraudWarning[] | undefined =>
	state.earlyFraudWarnings?.activeEarlyFraudWarnings;

export const getActiveEarlyFraudWarningsError = (
	state: State
): ApiError | undefined =>
	state.earlyFraudWarnings?.activeEarlyFraudWarningsError;
