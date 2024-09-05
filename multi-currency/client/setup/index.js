/** @format **/

/**
 * External dependencies
 */
import { useContext } from 'react';

/**
 * Internal dependencies
 */
import MultiCurrencySetup from './tasks/multi-currency-setup';
import { Page } from 'multi-currency/interface/components';
import {
	WooPaymentsContext,
	WooPaymentsContextV1,
} from 'multi-currency/interface/contexts';

const MultiCurrencySetupPage = () => {
	const { WCPaySettingsContext } = useContext( WooPaymentsContext );
	const { isSetupCompleted } = window.wcpaySettings.multiCurrencySetup;

	return (
		<Page>
			<WooPaymentsContextV1>
				<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
					<MultiCurrencySetup isSetupCompleted={ isSetupCompleted } />
				</WCPaySettingsContext.Provider>
			</WooPaymentsContextV1>
		</Page>
	);
};

export default MultiCurrencySetupPage;
