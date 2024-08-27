/** @format **/

/**
 * Internal dependencies
 */
import Page from 'components/page';
import MultiCurrencySetup from './tasks/multi-currency-setup';
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
