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
	isSameCard,
	PreferredCard,
} from './preferred-card-utils';
import { fetchPreferredCard } from './preferred-card-fetch';

/**
 * Hook that returns the user's preferred WooPay card.
 *
 * Initializes from localStorage cache for instant rendering, then queries
 * the WooPay Connect iframe for fresh data. Updates the cache and state
 * if the card has changed.
 */
const usePreferredCard = (): PreferredCard | null => {
	const [ preferredCard, setPreferredCard ] =
		useState< PreferredCard | null >( getCachedPreferredCard );

	useEffect( () => {
		fetchPreferredCard()
			.then( ( card ) => {
				setCachedPreferredCard( card );
				setPreferredCard( ( prev ) =>
					isSameCard( card, prev ) ? prev : card
				);
			} )
			.catch( () => {
				// Connect iframe unavailable — keep cached state.
			} );
	}, [] );

	return preferredCard;
};

export default usePreferredCard;
