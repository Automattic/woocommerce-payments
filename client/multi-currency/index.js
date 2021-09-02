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
import StoreSettings from './store-settings';

/**
 * Mount React Components
 */
const currencyContainer = document.getElementById(
	'wcpay_enabled_currencies_list'
);

let storeSettingsContainer;

if ( currencyContainer ) {
	ReactDOM.render( <EnabledCurrencies />, currencyContainer );

	// Create the Store Settings container
	storeSettingsContainer = document.createElement( 'div' );
	storeSettingsContainer.classList.add( 'wcpay_currencies_store_settings' );

	// Add it after currencyContainer
	currencyContainer.parentNode.insertBefore(
		storeSettingsContainer,
		currencyContainer.nextSibling
	);

	// Set by default to display none, will be displayed in toggleSettingsSectionDisplay if needed
	storeSettingsContainer.style.display = 'none';

	ReactDOM.render( <StoreSettings />, storeSettingsContainer );
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
			1 >= enabledCurrenciesListItemsExceptPlaceholders().length
				? 'none'
				: 'block';
		storeSettingsContainer.style.display = display;
		submitButton.style.display = display;
	};

	const enabledCurrenciesObserver = new MutationObserver(
		toggleSettingsSectionDisplay
	);

	enabledCurrenciesObserver.observe( enabledCurrenciesList, {
		childList: true,
	} );

	// Place the Store Settings element inside of storeSettingsContainer
	const storeSettingsContent = storeSettingsSection.getElementsByTagName(
		'td'
	)[ 0 ];

	// Obtain the container inside of StoreSettings component
	const storeSettingsContentContainer = storeSettingsContainer.getElementsByClassName(
		'store-settings__card-body'
	)[ 0 ];
	storeSettingsContentContainer.appendChild( storeSettingsContent );

	// Position Submit button
	submitButton.style.textAlign = 'center';

	toggleSettingsSectionDisplay();
}

function enabledCurrenciesListItemsExceptPlaceholders() {
	return Array.from( enabledCurrenciesList.children ).filter( ( item ) => {
		return (
			false === item.classList.contains( 'enabled-currency-placeholder' )
		);
	} );
}

const enabledCurrenciesOnboarding = document.querySelector(
	'#wcpay_enabled_currencies_onboarding_cta'
);

if ( enabledCurrenciesOnboarding ) {
	submitButton.style.display = 'none';
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
