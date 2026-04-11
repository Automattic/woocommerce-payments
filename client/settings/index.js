/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import enqueueFraudScripts from 'fraud-scripts';
import SettingsManager from './settings-manager';
import ExpressCheckoutSettings from './express-checkout-settings';
import WCPaySettingsContext from './wcpay-settings-context';
import ErrorBoundary from '../components/error-boundary';

window.addEventListener( 'load', () => {
	enqueueFraudScripts( wcpaySettings.fraudServices );
} );

const settingsContainer = document.getElementById(
	'wcpay-account-settings-container'
);
if ( settingsContainer ) {
	const root = createRoot( settingsContainer );
	root.render(
		<WCPaySettingsContext.Provider value={ wcpaySettings }>
			<ErrorBoundary>
				<SettingsManager />
			</ErrorBoundary>
		</WCPaySettingsContext.Provider>
	);
}

const expressCheckoutSettingsContainer = document.getElementById(
	'wcpay-express-checkout-settings-container'
);
if ( expressCheckoutSettingsContainer ) {
	const methodId = expressCheckoutSettingsContainer.dataset.methodId;
	const expressRoot = createRoot( expressCheckoutSettingsContainer );
	expressRoot.render(
		<WCPaySettingsContext.Provider value={ wcpaySettings }>
			<ErrorBoundary>
				<ExpressCheckoutSettings methodId={ methodId } />
			</ErrorBoundary>
		</WCPaySettingsContext.Provider>
	);
}
