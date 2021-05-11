/* global wcpay_tos_settings */

/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

import TosModal from './modal';
import showTosNotice from './disabled-notice';
import { maybeTrackStripeConnected } from './request.js';

// eslint-disable-next-line camelcase
if ( wcpay_tos_settings.tosAgreementRequired ) {
	renderTosModal();
} else {
	maybeTrackStripeConnected();
}

// eslint-disable-next-line camelcase
if ( wcpay_tos_settings.tosAgreementDeclined ) {
	/**
	 * ToDo: This is a temporary solution.
	 *
	 * The `core/notices` data layer is not loaded
	 * initially, we need to wait for it to be initialized.
	 */
	window.addEventListener( 'load', () => {
		// eslint-disable-next-line camelcase
		const { settingsUrl } = wcpay_tos_settings;

		showTosNotice( settingsUrl );
	} );
}

function renderTosModal() {
	const container = document.createElement( 'div' );
	container.id = 'wcpay-tos-container';
	document.body.appendChild( container );
	ReactDOM.render( <TosModal />, container );
}
