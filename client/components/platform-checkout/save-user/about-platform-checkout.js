/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

const AboutPlatformCheckout = () => {
	return (
		<div className="about-platform-checkout">
			{ interpolateComponents( {
				mixedString: __(
					"Enter your phone number to save your checkout information. You'll get {{strong}}secure single-click checkouts{{/strong}} here, and at 1,000s of other stores using Platform Checkout.",
					'woocommerce-payments'
				),
				components: { strong: <b /> },
			} ) }
		</div>
	);
};

export default AboutPlatformCheckout;
