/* global wcpay_tos_settings */

/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

import TosModal from './modal';
import showTosNotice from './disabled-notice';

// eslint-disable-next-line camelcase
if ( wcpay_tos_settings.tosAgreementRequired ) {
	renderTosModal();
}

// eslint-disable-next-line camelcase
if ( wcpay_tos_settings.tosAgreementDeclined ) {
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
