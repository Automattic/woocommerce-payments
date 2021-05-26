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
