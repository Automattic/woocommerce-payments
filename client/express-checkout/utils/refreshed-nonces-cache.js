/**
 * External dependencies
 */
import { isNil, omitBy } from 'lodash';

// Nonces the server sends again when the shopper logs in mid-checkout (their account is created, then the payment fails).
// The page's nonces belong to the logged-out shopper, so a retry needs these.
// Kept at module level because the retry can go through a different cart API instance.
let refreshedNonces = {};

// Returns whether the headers carried refreshed nonces.
export const rememberRefreshedNonces = ( headers ) => {
	const tokenizedCartNonce = headers?.get(
		'X-WooPayments-Tokenized-Cart-Nonce'
	);
	if ( ! tokenizedCartNonce ) {
		return false;
	}

	refreshedNonces = omitBy(
		{
			store_api_nonce: headers.get( 'Nonce' ),
			tokenized_cart_nonce: tokenizedCartNonce,
			tokenized_cart_session_nonce: headers.get(
				'X-WooPayments-Tokenized-Cart-Session-Nonce'
			),
		},
		isNil
	);

	return true;
};

export const getRefreshedNonces = () => refreshedNonces;

export const __resetRefreshedNoncesForTests = () => {
	refreshedNonces = {};
};
