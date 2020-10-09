/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

import TosModal from 'tos-modal';

renderTosModal();

function renderTosModal() {
	const container = document.createElement( 'div' );
	container.id = 'wcpay-tos-container';
	const wpcontent = document.getElementById( 'wpcontent' );
	wpcontent.appendChild( container );
	ReactDOM.render( <TosModal />, container );
}
