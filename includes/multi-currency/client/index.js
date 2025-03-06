/**
 * External dependencies
 */
import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import MultiCurrencySettings from './settings/multi-currency';
import SingleCurrencySettings from './settings/single-currency';
import MultiCurrencySettingsContext from './context';

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
		setCurrencyCodeToShowSettingsFor( code );
		setSingleCurrencyScreenOpen( true );
	};

	const handleCloseSingleCurrencySettings = () => {
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
				<MultiCurrencySettings />
			) : (
				<SingleCurrencySettings />
			) }
		</MultiCurrencySettingsContext.Provider>
	);
};

const root = createRoot(
	document.querySelector( '#wcpay_multi_currency_settings_container' )
);
root.render( <MultiCurrencySettingsPage /> );
