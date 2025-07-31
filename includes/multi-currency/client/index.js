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
import UnbundledWpComponentsProvider from 'wcpay/wordpress-components-context/unbundled-wp-components-provider';

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

const container = document.querySelector(
	'#wcpay_multi_currency_settings_container'
);
const root = createRoot( container );
root.render(
	<UnbundledWpComponentsProvider>
		<MultiCurrencySettingsPage />
	</UnbundledWpComponentsProvider>
);
