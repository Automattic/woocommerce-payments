/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import enqueueFraudScripts from 'fraud-scripts';
import SettingsManager from './settings-manager';
import ExpressCheckoutSettings from './express-checkout-settings';
import WCPaySettingsContext from './wcpay-settings-context';
import ErrorBoundary from '../components/error-boundary';
import UnbundledWpComponentsProvider from 'wcpay/wordpress-components-context/unbundled-wp-components-provider';

window.addEventListener( 'load', () => {
	enqueueFraudScripts( wcpaySettings.fraudServices );
} );

const settingsContainer = document.getElementById(
	'wcpay-account-settings-container'
);
if ( settingsContainer ) {
	ReactDOM.render(
		<UnbundledWpComponentsProvider>
			<WCPaySettingsContext.Provider value={ wcpaySettings }>
				<ErrorBoundary>
					<SettingsManager />
				</ErrorBoundary>
			</WCPaySettingsContext.Provider>
		</UnbundledWpComponentsProvider>,
		settingsContainer
	);
}

const expressCheckoutSettingsContainer = document.getElementById(
	'wcpay-express-checkout-settings-container'
);
if ( expressCheckoutSettingsContainer ) {
	const methodId = expressCheckoutSettingsContainer.dataset.methodId;

	ReactDOM.render(
		<UnbundledWpComponentsProvider>
			<WCPaySettingsContext.Provider value={ wcpaySettings }>
				<ErrorBoundary>
					<ExpressCheckoutSettings methodId={ methodId } />
				</ErrorBoundary>
			</WCPaySettingsContext.Provider>
		</UnbundledWpComponentsProvider>,
		expressCheckoutSettingsContainer
	);
}
