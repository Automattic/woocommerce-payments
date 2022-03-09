/** @format */

/**
 * External dependencies
 */
import { Experiment } from '@woocommerce/explat';

/**
 * Internal dependencies
 */
import DefaultExperience from 'connect-account-page';
import TreatmentExperience from './treatment';

const ConnectAccountPageExperiment = () => {
	return (
		<Experiment
			name="woo_wcpayments_welcome_screen_cta_click_202203_v1"
			defaultExperience={ <DefaultExperience /> }
			treatmentExperience={ <TreatmentExperience /> }
		/>
	);
};

export default ConnectAccountPageExperiment;
