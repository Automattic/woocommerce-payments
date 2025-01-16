/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import enqueueFraudScripts from 'fraud-scripts';
import SettingsManager from 'settings/settings-manager';
import ExpressCheckoutSettings from './express-checkout-settings';
import WCPaySettingsContext from './wcpay-settings-context';
import AdminErrorBoundary from '../components/admin-error-boundary';

window.addEventListener( 'load', () => {
	enqueueFraudScripts( wcpaySettings.fraudServices );
} );

const settingsContainer = document.getElementById(
	'wcpay-account-settings-container'
);
if ( settingsContainer ) {
	ReactDOM.render(
		<WCPaySettingsContext.Provider value={ wcpaySettings }>
			<AdminErrorBoundary>
				<SettingsManager />
			</AdminErrorBoundary>
		</WCPaySettingsContext.Provider>,
		settingsContainer
	);
}

const expressCheckoutSettingsContainer = document.getElementById(
	'wcpay-express-checkout-settings-container'
);
if ( expressCheckoutSettingsContainer ) {
	const methodId = expressCheckoutSettingsContainer.dataset.methodId;

	ReactDOM.render(
		<WCPaySettingsContext.Provider value={ wcpaySettings }>
			<AdminErrorBoundary>
				<ExpressCheckoutSettings methodId={ methodId } />
			</AdminErrorBoundary>
		</WCPaySettingsContext.Provider>,
		expressCheckoutSettingsContainer
	);
}
