/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import CurrencyTable from './currency-table';
//  import './style.scss';
//  import PaymentMethodSettings from './payment-method-settings';

const currencyContainer = document.getElementById(
	'wcpay_enabled_currencies_list'
);
// eslint-disable-next-line no-undef
if ( currencyContainer && wcpayMultiCurrencySettings ) {
	ReactDOM.render( <CurrencyTable />, currencyContainer );
}
