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
		currencyCodeToShowSettingsFor,
		setCurrencyCodeToShowSettingsFor,
	] = useState( null );

	return (
		<MultiCurrencySettingsContext.Provider
			value={ {
				currencyCodeToShowSettingsFor,
				setCurrencyCodeToShowSettingsFor,
			} }
		>
			{ ! currencyCodeToShowSettingsFor ? (
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
