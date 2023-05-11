/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const AdditionalInformation = () => {
	return (
		<div className="additional-information">
			{ __(
				'Next time you checkout here or on other WooPay enabled stores, you’ll receive ' +
					'a code by text message to checkout quicker. We never share your full financial information with sellers.',
				'woocommerce-payments'
			) }
		</div>
	);
};

export default AdditionalInformation;
