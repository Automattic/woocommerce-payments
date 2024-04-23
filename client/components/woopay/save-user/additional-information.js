/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const AdditionalInformation = () => {
	return (
		<div className="additional-information">
			{ __(
				"Next time you buy here and on other Woo-powered stores, we'll send you a code to securely purchase with WooPay.",
				'woocommerce-payments'
			) }
		</div>
	);
};

export default AdditionalInformation;
