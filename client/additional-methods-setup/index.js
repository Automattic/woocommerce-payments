/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import UpePreviewMethodSelector from './upe-preview-methods-selector';
import WcPayUpeContextProvider from '../settings/wcpay-upe-toggle/provider';
import WCPaySettingsContext from '../settings/wcpay-settings-context';

const AdditionalMethodsPage = () => {
	const { isUpeEnabled } = window.wcpaySettings.additionalMethodsSetup;

	return (
		<Page>
			<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
				<WcPayUpeContextProvider defaultIsUpeEnabled={ isUpeEnabled }>
					<UpePreviewMethodSelector />
				</WcPayUpeContextProvider>
			</WCPaySettingsContext.Provider>
		</Page>
	);
};

export default AdditionalMethodsPage;
