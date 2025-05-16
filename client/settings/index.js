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
import ErrorBoundary from '../components/error-boundary';
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

window.addEventListener( 'load', () => {
	enqueueFraudScripts( wcpaySettings.fraudServices );
} );

const settingsContainer = document.getElementById(
	'wcpay-account-settings-container'
);
if ( settingsContainer ) {
	ReactDOM.render(
		<WordPressComponentsContext.Provider value={ wp.components }>
			<WCPaySettingsContext.Provider value={ wcpaySettings }>
				<ErrorBoundary>
					<SettingsManager />
				</ErrorBoundary>
			</WCPaySettingsContext.Provider>
		</WordPressComponentsContext.Provider>,
		settingsContainer
	);
}

const expressCheckoutSettingsContainer = document.getElementById(
	'wcpay-express-checkout-settings-container'
);
if ( expressCheckoutSettingsContainer ) {
	const methodId = expressCheckoutSettingsContainer.dataset.methodId;

	ReactDOM.render(
		<WordPressComponentsContext.Provider value={ wp.components }>
			<WCPaySettingsContext.Provider value={ wcpaySettings }>
				<ErrorBoundary>
					<ExpressCheckoutSettings methodId={ methodId } />
				</ErrorBoundary>
			</WCPaySettingsContext.Provider>
		</WordPressComponentsContext.Provider>,
		expressCheckoutSettingsContainer
	);
}
