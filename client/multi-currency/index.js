/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import EnabledCurrencies from './enabled-currencies-list';

const currencyContainer = document.getElementById(
	'wcpay_enabled_currencies_list'
);

if ( currencyContainer ) {
	ReactDOM.render( <EnabledCurrencies />, currencyContainer );
}

const hideShowManualField = ( show ) => {
	const manualRateField = document
		.querySelector( '[id^=wcpay_multi_currency_manual_rate_]' )
		.closest( 'tr' );
	manualRateField.style.display = show ? 'table-row' : 'none';
};

const triggerHideShow = ( value, checked ) => {
	hideShowManualField( 'manual' === value && true === checked );
};

document.querySelectorAll( '.exchange-rate-selector' ).forEach( ( radio ) => {
	triggerHideShow( radio.value, radio.checked );

	radio.addEventListener( 'change', ( event ) => {
		triggerHideShow( event.target.value, event.target.checked );
	} );
} );
