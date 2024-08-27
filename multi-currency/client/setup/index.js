/** @format **/

/**
 * Internal dependencies
 */
import MultiCurrencySetup from './tasks/multi-currency-setup';
import { Page } from 'mccy/interface/components';
import { WCPaySettingsContext } from 'mccy/interface/functions';

const MultiCurrencySetupPage = () => {
	const { isSetupCompleted } = window.wcpaySettings.multiCurrencySetup;

	return (
		<Page>
			<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
				<MultiCurrencySetup isSetupCompleted={ isSetupCompleted } />
			</WCPaySettingsContext.Provider>
		</Page>
	);
};

export default MultiCurrencySetupPage;
