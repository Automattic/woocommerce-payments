/**
 * External dependencies
 */
import React, { useState } from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import enqueueFraudScripts from 'fraud-scripts';
import SettingsManager from 'settings/settings-manager';
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
	const SettingsWrapper = () => {
		const [ hasChanges, setHasChanges ] = useState( false );

		return (
			<WCPaySettingsContext.Provider
				value={ {
					...wcpaySettings,
					hasChanges,
					setHasChanges,
				} }
			>
				<ErrorBoundary>
					<SettingsManager />
				</ErrorBoundary>
			</WCPaySettingsContext.Provider>
		);
	};

	ReactDOM.render( <SettingsWrapper />, settingsContainer );
}

const expressCheckoutSettingsContainer = document.getElementById(
	'wcpay-express-checkout-settings-container'
);
if ( expressCheckoutSettingsContainer ) {
	const methodId = expressCheckoutSettingsContainer.dataset.methodId;

	ReactDOM.render(
		<WCPaySettingsContext.Provider value={ wcpaySettings }>
			<ErrorBoundary>
				<ExpressCheckoutSettings methodId={ methodId } />
			</ErrorBoundary>
		</WCPaySettingsContext.Provider>,
		expressCheckoutSettingsContainer
	);
}
