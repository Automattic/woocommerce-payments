/* eslint-disable max-len */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

const Agreement = ( { isSaving = false } ) => {
	return (
		<div className="tos">
			{ interpolateComponents( {
				mixedString: __(
					'By entering your phone number and {{action/}}, you will create a Platform Checkout account and agree to {{termsOfService/}} and {{privacyPolicy/}}.',
					'woocommerce-payments'
				),
				components: {
					action: (
						<span>
							{ isSaving
								? __( 'clicking save', 'woocommerce-payments' )
								: __(
										'completing your purchase',
										'woocommerce-payments'
								  ) }
						</span>
					),
					termsOfService: (
						<a href="?TODO">
							{ __( 'Terms of Service', 'woocommerce-payments' ) }
						</a>
					),
					privacyPolicy: (
						<a href="?TODO">
							{ __( 'Privacy Policy', 'woocommerce-payments' ) }
						</a>
					),
				},
			} ) }
		</div>
	);
};

export default Agreement;
