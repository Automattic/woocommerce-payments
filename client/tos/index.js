/* global wcpay_tos_settings */

/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

import TosModal from './modal';
import TosSnackbar from './snackbar';

// eslint-disable-next-line camelcase
if ( wcpay_tos_settings.showModal ) {
	renderTosModal();
}

// eslint-disable-next-line camelcase
if ( wcpay_tos_settings.showSnackbar ) {
	renderTosSnackbar();
}

function renderTosModal() {
	// eslint-disable-next-line camelcase
	const { disabledSettingsUrl } = wcpay_tos_settings;

	const container = document.createElement( 'div' );
	container.id = 'wcpay-tos-container';
	const wpcontent = document.getElementById( 'wpcontent' );
	wpcontent.appendChild( container );
	ReactDOM.render(
		<TosModal settingsUrl={ disabledSettingsUrl } />,
		container
	);
}

function renderTosSnackbar() {
	// eslint-disable-next-line camelcase
	const { settingsUrl } = wcpay_tos_settings;

	const container = document.createElement( 'div' );
	container.className = 'woocommerce-payments__tos-snackbar';
	const wpcontent = document.getElementById( 'wpcontent' );
	wpcontent.appendChild( container );
	ReactDOM.render( <TosSnackbar settingsUrl={ settingsUrl } />, container );
}
