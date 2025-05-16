/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { recordUserEvent } from 'tracks';
import { ExternalLink } from 'wcpay/components/wp-components-wrapped';

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
						<ExternalLink
							href="https://wordpress.com/tos/"
							onClick={ () => {
								recordUserEvent(
									'checkout_save_my_info_tos_click'
								);
							} }
						>
							{ __( 'Terms of Service', 'woocommerce-payments' ) }
						</ExternalLink>
					),
					privacyPolicy: (
						<ExternalLink
							href="https://automattic.com/privacy/"
							onClick={ () => {
								recordUserEvent(
									'checkout_save_my_info_privacy_policy_click'
								);
							} }
						>
							{ __( 'Privacy Policy', 'woocommerce-payments' ) }
						</ExternalLink>
					),
				},
			} ) }
		</div>
	);
};

export default Agreement;
