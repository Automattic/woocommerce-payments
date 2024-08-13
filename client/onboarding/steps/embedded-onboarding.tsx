/**
 * External dependencies
 */
import React, { useState } from 'react';

/**
 * Internal dependencies
 */

const EmbeddedOnboarding: React.FC = () => {
	// We use `useState` to ensure the Connect instance is only initialized once
	const [ stripeConnectInstance ] = useState( () => {
		const fetchClientSecret = async () => {
			// Fetch the AccountSession client secret
			const response = await fetch('/account_session', { method: "POST" });
			if (!response.ok) {
				// Handle errors on the client side here
				const {error} = await response.json();
				console.error('An error occurred: ', error);
				document.querySelector('#error').removeAttribute('hidden');
				return undefined;
			} else {
				const {client_secret: clientSecret} = await response.json();
				document.querySelector('#error').setAttribute('hidden', '');
				return clientSecret;
			}
		}

		return loadConnectAndInitialize({
			// This is your test publishable API key.
			publishableKey: "pk_test_qblFNYngBkEdjEZ16jxxoWSM",
			fetchClientSecret: fetchClientSecret,
		})
	});
};

export default EmbeddedOnboarding;
