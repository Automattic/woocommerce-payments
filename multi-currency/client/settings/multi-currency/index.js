/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import SettingsLayout from 'multi-currency/components/settings-layout';
import EnabledCurrenciesList from './enabled-currencies-list';
import StoreSettings from './store-settings';
import './style.scss';

const MultiCurrencySettings = () => {
	return (
		<div className="multi-currency-settings">
			<SettingsLayout>
				<EnabledCurrenciesList />
				<StoreSettings />
			</SettingsLayout>
		</div>
	);
};

export default MultiCurrencySettings;
