/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

const Agreement = () => {
	return (
		<div className="tos">
			{ interpolateComponents( {
				mixedString: __(
					"By placing an order, you agree to WooPay's {{termsOfService/}} and {{privacyPolicy/}}, and to receive text messages at the mobile number provided.",
					'woocommerce-payments'
				),
				components: {
					termsOfService: (
						<a
							target="_blank"
							href="https://wordpress.com/tos/"
							rel="noopener noreferrer"
						>
							{ __( 'Terms of Service', 'woocommerce-payments' ) }
						</a>
					),
					privacyPolicy: (
						<a
							target="_blank"
							href="https://automattic.com/privacy/"
							rel="noopener noreferrer"
						>
							{ __( 'Privacy Policy', 'woocommerce-payments' ) }
						</a>
					),
				},
			} ) }
		</div>
	);
};

export default Agreement;
