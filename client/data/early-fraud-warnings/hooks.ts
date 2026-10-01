/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './store';
import {
	ActiveEarlyFraudWarning,
	ActiveEarlyFraudWarningsResponse,
} from './types';

// One shared instance: a fresh [] fails useSelect's shallow compare, so the Overview
// would re-render on every store change until the resolver settles.
const noWarnings: ActiveEarlyFraudWarning[] = [];

export const useActiveEarlyFraudWarnings =
	(): ActiveEarlyFraudWarningsResponse =>
		useSelect( ( select ) => {
			const {
				getActiveEarlyFraudWarnings,
				getActiveEarlyFraudWarningsError,
				hasFinishedResolution,
			} = select( STORE_NAME );

			const activeEarlyFraudWarnings:
				| ActiveEarlyFraudWarning[]
				| undefined = getActiveEarlyFraudWarnings();

			return {
				activeEarlyFraudWarnings:
					activeEarlyFraudWarnings ?? noWarnings,
				activeEarlyFraudWarningsError:
					getActiveEarlyFraudWarningsError(),
				// A stored list counts as loaded during a refetch, so refreshing it after a
				// dismissal doesn't blank every other task. Otherwise gate on resolution
				// finishing: isResolving is false before the resolver starts.
				hasLoaded:
					activeEarlyFraudWarnings !== undefined ||
					hasFinishedResolution( 'getActiveEarlyFraudWarnings' ),
			};
		}, [] );
