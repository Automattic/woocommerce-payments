/** @format **/
/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { getHistory, getNewPath } from '@woocommerce/navigation';

/* global wcpayAdditionalMethodsSetup */
export const markTaskComplete = () => {
	apiFetch( {
		path: '/wc-admin/options',
		method: 'POST',
		// eslint-disable-next-line camelcase
		data: { wcpay_additional_methods_setup_completed: 'yes' },
	} )
		.then( () => {
			// Set the local `isSetupCompleted` to `yes` so that task appears completed on the list.
			// Please note that marking an item as "completed" is different from "dismissing" it.
			wcpayAdditionalMethodsSetup.isSetupCompleted = 'yes';
			// Redirect back to the root WooCommerce Admin page.
			getHistory().push( getNewPath( {}, '/', {} ) );
		} )
		.catch( () => {
			// Something went wrong with our update. Swallow the error.
		} );
};
