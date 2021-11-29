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
	const {
		isUpeSettingsPreviewEnabled,
		isUpeEnabled,
	} = window.wcpaySettings.additionalMethodsSetup;

	if ( isUpeSettingsPreviewEnabled ) {
		return (
			<Page>
				<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
					<WcPayUpeContextProvider
						defaultIsUpeEnabled={ isUpeEnabled }
					>
						<UpePreviewMethodSelector />
					</WcPayUpeContextProvider>
				</WCPaySettingsContext.Provider>
			</Page>
		);
	}

	return null;
};

export default AdditionalMethodsPage;
