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

			return {
				activeEarlyFraudWarnings:
					getActiveEarlyFraudWarnings() ?? noWarnings,
				activeEarlyFraudWarningsError:
					getActiveEarlyFraudWarningsError(),
				// Gate on resolution having finished rather than on isResolving, which is
				// false before the resolver starts and would read as "no warnings".
				hasLoaded: hasFinishedResolution(
					'getActiveEarlyFraudWarnings'
				),
			};
		}, [] );
