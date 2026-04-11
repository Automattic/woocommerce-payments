/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { recordUserEvent } from 'tracks';

const Agreement = () => {
	return (
		<div className="tos">
			{ interpolateComponents( {
				mixedString: __(
					"By continuing, you agree to WooPay's {{termsOfService/}} and {{privacyPolicy/}}.",
					'woocommerce-payments'
				),
				components: {
					termsOfService: (
						<a
							target="_blank"
							href="https://wordpress.com/tos/"
							rel="noopener noreferrer"
							onClick={ () => {
								recordUserEvent(
									'checkout_save_my_info_tos_click'
								);
							} }
						>
							{ __( 'Terms of Service', 'woocommerce-payments' ) }
						</a>
					),
					privacyPolicy: (
						<a
							target="_blank"
							href="https://automattic.com/privacy/"
							rel="noopener noreferrer"
							onClick={ () => {
								recordUserEvent(
									'checkout_save_my_info_privacy_policy_click'
								);
							} }
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
