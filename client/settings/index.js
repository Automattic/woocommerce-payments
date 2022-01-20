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
import PaymentMethodSettings from './payment-method-settings';
import WCPaySettingsContext from './wcpay-settings-context';
import ErrorBoundary from '../components/error-boundary';

window.addEventListener( 'load', () => {
	enqueueFraudScripts( wcpaySettings.fraudServices );
} );

const settingsContainer = document.getElementById(
	'wcpay-account-settings-container'
);
if ( settingsContainer ) {
	ReactDOM.render(
		<WCPaySettingsContext.Provider value={ wcpaySettings }>
			<ErrorBoundary>
				<SettingsManager />
			</ErrorBoundary>
		</WCPaySettingsContext.Provider>,
		settingsContainer
	);
}

const paymentMethodSettingsContainer = document.getElementById(
	'wcpay-payment-method-settings-container'
);
if ( paymentMethodSettingsContainer ) {
	const methodId = paymentMethodSettingsContainer.dataset.methodId;

	ReactDOM.render(
		<ErrorBoundary>
			<PaymentMethodSettings methodId={ methodId } />
		</ErrorBoundary>,
		paymentMethodSettingsContainer
	);
}
