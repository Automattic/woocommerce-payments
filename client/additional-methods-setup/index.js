/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import UpePreviewMethodSelector from './upe-preview-methods-selector';
import WCPaySettingsContext from '../settings/wcpay-settings-context';

const AdditionalMethodsPage = () => {
	return (
		<Page>
			<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
				<UpePreviewMethodSelector />
			</WCPaySettingsContext.Provider>
		</Page>
	);
};

export default AdditionalMethodsPage;
