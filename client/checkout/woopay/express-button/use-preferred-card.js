/**
 * External dependencies
 */
import { useState, useEffect } from 'react';

/**
 * Internal dependencies
 */
import {
	getCachedPreferredCard,
	setCachedPreferredCard,
	fetchPreferredCard,
} from './preferred-card-utils';

/**
 * Hook that returns the user's preferred WooPay card.
 *
 * Initializes from localStorage cache for instant rendering, then queries
 * the WooPay Connect iframe for fresh data. Updates the cache and state
 * if the card has changed.
 *
 * @return {Object|null} The preferred card ({ brand, last4 }) or null.
 */
const usePreferredCard = () => {
	const [ preferredCard, setPreferredCard ] = useState(
		getCachedPreferredCard
	);

	useEffect( () => {
		fetchPreferredCard()
			.then( ( card ) => {
				setCachedPreferredCard( card );
				setPreferredCard( card );
			} )
			.catch( () => {
				// Connect iframe unavailable — keep cached state.
			} );
	}, [] );

	return preferredCard;
};

export default usePreferredCard;
