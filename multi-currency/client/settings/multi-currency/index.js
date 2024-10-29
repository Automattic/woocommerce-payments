/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { SettingsLayout } from 'multi-currency/interface/components';
import EnabledCurrenciesList from './enabled-currencies-list';
import StoreSettings from './store-settings';
import './style.scss';

const MultiCurrencySettings = () => {
	return (
		<div className="multi-currency-settings">
			<SettingsLayout displayBanner={ false }>
				<EnabledCurrenciesList />
				<StoreSettings />
			</SettingsLayout>
		</div>
	);
};

export default MultiCurrencySettings;
