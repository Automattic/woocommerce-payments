/**
 * External dependencies
 */
import React, { useState } from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import EnabledCurrencies from './enabled-currencies-list';
import SingleCurrencySettings from './single-currency-settings';
import MultiCurrencySettingsContext from './context';

const currencyContainer = document.getElementById(
	'wcpay_enabled_currencies_list'
);

const storeSettingsSection = document.querySelector(
	'#wcpay_currencies_settings_section'
);

const submitButton = document.querySelector( 'p.submit' );

const displayStoreSettingsSection = ( isDisplaying ) => {
	storeSettingsSection.style.display = isDisplaying ? 'block' : 'none';
	submitButton.style.display = isDisplaying ? 'block' : 'none';
};

const MultiCurrencySettingsPage = () => {
	const [
		isSingleCurrencyScreenOpen,
		setSingleCurrencyScreenOpen,
	] = useState( false );
	const [
		currencyCodeToShowSettingsFor,
		setCurrencyCodeToShowSettingsFor,
	] = useState( null );
	const handleOpenSingleCurrencySettings = ( code ) => {
		displayStoreSettingsSection( false );
		setCurrencyCodeToShowSettingsFor( code );
		setSingleCurrencyScreenOpen( true );
	};
	const handleCloseSingleCurrencySettings = () => {
		const display =
			0 < enabledCurrenciesListItemsExceptPlaceholders().length;
		displayStoreSettingsSection( display );
		setSingleCurrencyScreenOpen( false );
		setCurrencyCodeToShowSettingsFor( null );
	};

	return (
		<MultiCurrencySettingsContext.Provider
			value={ {
				isSingleCurrencyScreenOpen: isSingleCurrencyScreenOpen,
				currencyCodeToShowSettingsFor: currencyCodeToShowSettingsFor,
				openSingleCurrencySettings: handleOpenSingleCurrencySettings,
				closeSingleCurrencySettings: handleCloseSingleCurrencySettings,
			} }
		>
			{ ! isSingleCurrencyScreenOpen ? (
				<EnabledCurrencies />
			) : (
				<SingleCurrencySettings />
			) }
		</MultiCurrencySettingsContext.Provider>
	);
};

/**
 * Mount React Component
 */
if ( currencyContainer ) {
	ReactDOM.render( <MultiCurrencySettingsPage />, currencyContainer );
}

/**
 * Store settings section
 */
const enabledCurrenciesList = document.querySelector(
	'.enabled-currencies-list'
);

if ( storeSettingsSection ) {
	const toggleSettingsSectionDisplay = () => {
		const display =
			0 < enabledCurrenciesListItemsExceptPlaceholders().length;
		displayStoreSettingsSection( display );
	};

	const enabledCurrenciesObserver = new MutationObserver(
		toggleSettingsSectionDisplay
	);

	enabledCurrenciesObserver.observe( enabledCurrenciesList, {
		childList: true,
	} );

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
