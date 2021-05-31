/** @format */

/**
 * External dependencies
 */
import { Experiment } from '@woocommerce/explat';

/**
 * Internal dependencies
 */
import ConnectAccountPage from 'connect-account-page';
import ConnectAccountPageVariant from 'connect-account-page-variant';

const ConnectAccountPageExperiment = () => {
	return (
		<Experiment
			name="wcpay_inapp_payments_connect_page_v2"
			defaultExperience={ <ConnectAccountPage /> }
			treatmentExperience={ <ConnectAccountPageVariant /> }
			loadingExperience={ null }
		/>
	);
};

export default ConnectAccountPageExperiment;
