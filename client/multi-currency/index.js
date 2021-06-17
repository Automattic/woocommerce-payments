/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import EnabledCurrencies from './enabled-currencies-list';

/**
 * Mount React Component
 */
const currencyContainer = document.getElementById(
	'wcpay_enabled_currencies_list'
);

if ( currencyContainer ) {
	ReactDOM.render( <EnabledCurrencies />, currencyContainer );
}

/**
 * Single currency settings
 */
let rateType = 'automatic';

const automaticRate = document.querySelector(
	'[name=wcpay_multi_currency_automatic_exchange_rate]'
);

const manualRate = document.querySelector(
	'[name^=wcpay_multi_currency_manual_rate_]'
);

const rounding = document.querySelector(
	'[name^=wcpay_multi_currency_price_rounding_]'
);

const charm = document.querySelector(
	'[name^=wcpay_multi_currency_price_charm_]'
);

const previewAmount = document.querySelector(
	'#wcpay_multi_currency_preview_default'
);

const previewDisplay = document.querySelector(
	'#wcpay_multi_currency_preview_converted span'
);

function updatePreview() {
	// Get needed field values and update field.
	const rate = 'manual' === rateType ? manualRate.value : automaticRate.value;
	let total = previewAmount.value * rate;

	if ( 'none' !== rounding.value ) {
		const precision = Math.pow( 10, rounding.value );
		total = Math.ceil( total * precision ) / precision;
	}

	total += parseFloat( charm.value );
	total = total.toFixed( 2 );
	total = isNaN( total )
		? __( 'Please enter a valid number', 'woocommerce-payments' )
		: total;
	previewDisplay.innerHTML = total;
}

const hideShowManualField = ( show ) => {
	const manualRateField = document
		.querySelector( '[id^=wcpay_multi_currency_manual_rate_]' )
		.closest( 'tr' );
	manualRateField.style.display = show ? 'table-row' : 'none';
	rateType = show ? 'manual' : 'automatic';
	updatePreview();
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

[ manualRate, rounding, charm, previewAmount ]
	.filter( ( _ ) => _ )
	.forEach( ( element ) =>
		element.addEventListener( 'input', () => updatePreview() )
	);

/**
 * Store settings section
 */
const enabledCurrenciesList = document.querySelector(
	'.enabled-currencies-list'
);
const storeSettingsSection = document.querySelector(
	'#wcpay_currencies_settings_section'
);

const enabledCurrenciesObserver = new MutationObserver( () => {
	storeSettingsSection.style.display =
		1 >= enabledCurrenciesList.children.length ? 'none' : 'block';
} );

enabledCurrenciesObserver.observe( enabledCurrenciesList, {
	childList: true,
} );
