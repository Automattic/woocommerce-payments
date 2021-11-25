/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import MethodSelector from './methods-selector';
import UpePreviewMethodSelector from './upe-preview-methods-selector';
import WcPayUpeContextProvider from '../settings/wcpay-upe-toggle/provider';
import WCPaySettingsContext from '../settings/wcpay-settings-context';

const AdditionalMethodsPage = () => {
	const {
		isUpeSettingsPreviewEnabled,
		isUpeEnabled,
	} = window.wcpaySettings.additionalMethodsSetup;

	const additionalMethodsContent = isUpeSettingsPreviewEnabled ? (
		<WcPayUpeContextProvider defaultIsUpeEnabled={ isUpeEnabled }>
			<UpePreviewMethodSelector />
		</WcPayUpeContextProvider>
	) : (
		<MethodSelector />
	);

	return (
		<Page>
			<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
				{ additionalMethodsContent }
			</WCPaySettingsContext.Provider>
		</Page>
	);
};

export default AdditionalMethodsPage;
