/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './store';
import { ActiveEarlyFraudWarningsResponse } from './types';

export const useActiveEarlyFraudWarnings =
	(): ActiveEarlyFraudWarningsResponse =>
		useSelect( ( select ) => {
			const {
				getActiveEarlyFraudWarnings,
				getActiveEarlyFraudWarningsError,
				hasFinishedResolution,
			} = select( STORE_NAME );

			return {
				activeEarlyFraudWarnings: getActiveEarlyFraudWarnings() ?? [],
				activeEarlyFraudWarningsError:
					getActiveEarlyFraudWarningsError(),
				// Gate on resolution having finished rather than on isResolving, which is
				// false before the resolver starts and would read as "no warnings".
				hasLoaded: hasFinishedResolution(
					'getActiveEarlyFraudWarnings'
				),
			};
		}, [] );
