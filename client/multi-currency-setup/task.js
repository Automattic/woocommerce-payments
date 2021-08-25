/** @format **/
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { getPath, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import CurrencySelector from './currency-selector';
import WCPaySettingsContext from '../settings/wcpay-settings-context';

const createMultiCurrencySetupTask = ( { isSetupCompleted } ) => {
	const key = 'woocommerce-payments--multi-currency-setup';

	return {
		key,
		title: __(
			'Sell worldwide in multiple currencies',
			'woocommerce-payments'
		),
		// it might be worth exploring how to use Suspense
		// to lazily load the JS that might not be necessary if the action is not taken.
		// It might also be worth exploring adding an error boundary to prevent the whole page to be blank in case of error?
		container: (
			<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
				<CurrencySelector />
			</WCPaySettingsContext.Provider>
		),
		// please note: marking an item as "dismissed" does not mean it's "completed" - they are considered 2 different things
		completed: 'yes' === isSetupCompleted,
		visible: true,
		additionalInfo: null,
		isDismissable: true,

		...( '/multi_currency/overview' === getPath()
			? {
					onClick: () => {
						updateQueryString( { task: key }, '' );
					},
			  }
			: {} ),
	};
};

export default createMultiCurrencySetupTask;
