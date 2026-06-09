/**
 * Internal dependencies
 */
import WooPayUserConnect from 'wcpay/checkout/woopay/connect/user-connect';
import { isValidPreferredCard, PreferredCard } from './preferred-card-utils';

let cache: Promise< PreferredCard | null > | undefined;

/**
 * Queries the WooPay Connect iframe for the user's preferred payment method.
 *
 * The result is cached for the lifetime of the page (module-scoped promise).
 * Concurrent callers (e.g. blocks `usePreferredCard` hook + classic `index.js`
 * `window.load` handler) and any later callers (cart updates, re-renders)
 * all share the same promise — we only message the iframe once per page load.
 */
export const fetchPreferredCard = (): Promise< PreferredCard | null > => {
	if ( ! cache ) {
		cache = ( async () => {
			const userConnect = new WooPayUserConnect();
			try {
				const card = await userConnect.getPreferredPaymentMethod();
				return isValidPreferredCard( card ) ? card : null;
			} finally {
				userConnect.detachMessageListener();
			}
		} )();
	}
	return cache;
};
