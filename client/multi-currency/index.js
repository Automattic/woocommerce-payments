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
 * Store settings section
 */
const enabledCurrenciesList = document.querySelector(
	'.enabled-currencies-list'
);
const storeSettingsSection = document.querySelector(
	'#wcpay_currencies_settings_section'
);
const submitButton = document.querySelector( 'p.submit' );

if ( storeSettingsSection ) {
	const toggleSettingsSectionDisplay = () => {
		const display =
			1 >= enabledCurrenciesList.children.length ? 'none' : 'block';
		storeSettingsSection.style.display = display;
		submitButton.style.display = display;
	};

	const enabledCurrenciesObserver = new MutationObserver(
		toggleSettingsSectionDisplay
	);

	enabledCurrenciesObserver.observe( enabledCurrenciesList, {
		childList: true,
	} );

	toggleSettingsSectionDisplay();
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
	const currencyCode = new URLSearchParams( document.location.search )
		.get( 'section' )
		.toUpperCase();
	let total = previewAmount.value * rate;

	if ( 'none' !== rounding.value ) {
		total = Math.ceil( total / rounding.value ) * rounding.value;
	}

	total += parseFloat( charm.value );
	if ( isNaN( total ) ) {
		previewDisplay.innerHTML = __(
			'Please enter a valid number',
			'woocommerce-payments'
		);
		return;
	}

	previewDisplay.innerHTML = total.toLocaleString(
		undefined, // Use the default locale for the given currency.
		{
			style: 'currency',
			currency: currencyCode,
			currencyDisplay: 'narrowSymbol',
		}
	);
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
