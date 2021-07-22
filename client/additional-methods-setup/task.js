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
import MethodSelector from './methods-selector';
import UpePreviewMethodSelector from './upe-preview-methods-selector';
import WcPayUpeContextProvider from '../settings/wcpay-upe-toggle/provider';
import WCPaySettingsContext from '../settings/wcpay-settings-context';

const createAdditionalMethodsSetupTask = ( {
	isSetupCompleted,
	isUpeEnabled,
	isUpeSettingsPreviewEnabled,
} ) => {
	const key = 'woocommerce-payments--additional-payment-methods';

	return {
		key,
		title: __(
			'Set up additional payment methods',
			'woocommerce-payments'
		),
		// it might be worth exploring how to use Suspense
		// to lazily load the JS that might not be necessary if the action is not taken.
		// It might also be worth exploring adding an error boundary to prevent the whole page to be blank in case of error?
		container: (
			<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
				<MethodSelector />
			</WCPaySettingsContext.Provider>
		),
		// please note: marking an item as "dismissed" does not mean it's "completed" - they are considered 2 different things
		completed: 'yes' === isSetupCompleted,
		visible: true,
		additionalInfo: __(
			'Offer your customers preferred payment methods with WooCommerce Payments',
			'woocommerce-payments'
		),
		isDismissable: true,

		...( '/payments/overview' === getPath()
			? {
					onClick: () => {
						updateQueryString( { task: key }, '' );
					},
			  }
			: {} ),

		// overwriting the default values, while the test is running
		// using object spread to override the attributes makes things a bit easier when it'll be time to clean up
		...( isUpeSettingsPreviewEnabled
			? {
					additionalInfo: __(
						'Get early access to additional payment methods and an improved checkout experience',
						'woocommerce-payments'
					),
					title: __(
						'Boost your sales by accepting new payment methods',
						'woocommerce-payments'
					),
					container: (
						<WCPaySettingsContext.Provider
							value={ window.wcpaySettings }
						>
							<WcPayUpeContextProvider
								defaultIsUpeEnabled={ isUpeEnabled }
							>
								<UpePreviewMethodSelector />
							</WcPayUpeContextProvider>
						</WCPaySettingsContext.Provider>
					),
					completed:
						// eslint-disable-next-line max-len
						'yes' === isSetupCompleted || isUpeEnabled,
			  }
			: {} ),
	};
};

export default createAdditionalMethodsSetupTask;
