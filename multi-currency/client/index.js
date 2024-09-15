/**
 * External dependencies
 */
import React, { useState } from 'react';
import ReactDOM from 'react-dom';

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

	const [ isDirty, setIsDirty ] = useState( false );

	return (
		<MultiCurrencySettingsContext.Provider
			value={ {
				isSingleCurrencyScreenOpen: isSingleCurrencyScreenOpen,
				currencyCodeToShowSettingsFor: currencyCodeToShowSettingsFor,
				openSingleCurrencySettings: handleOpenSingleCurrencySettings,
				closeSingleCurrencySettings: handleCloseSingleCurrencySettings,
				isDirty,
				setIsDirty,
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

ReactDOM.render(
	<MultiCurrencySettingsPage />,
	document.querySelector( '#wcpay_multi_currency_settings_container' )
);
