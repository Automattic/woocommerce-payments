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

window.onload = () => {
	const exchangeRadio = document.querySelectorAll(
		'.exchange-rate-selector'
	);
	for ( let i = 0; i < exchangeRadio.length; i++ ) {
		const element = exchangeRadio[ i ];
		triggerHideShow( element.value, element.checked );

		element.addEventListener( 'change', ( event ) => {
			triggerHideShow( event.target.value, event.target.checked );
		} );
	}
};
