/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import LockIcon from '../icons/lock';
import PhoneIcon from '../icons/phone';

const AdditionalInformation = () => {
	return (
		<div>
			<div className="additional-information">
				<div>
					<PhoneIcon />
				</div>
				<span>
					{ interpolateComponents( {
						mixedString: __(
							'Enter your mobile phone number to save your checkout information for faster checkouts here, ' +
								'and at other stores powered by {{wooPayLink/}}.',
							'woocommerce-payments'
						),
						components: {
							wooPayLink: (
								<a
									target="_blank"
									href="https://woocommerce.com/woopay/"
									rel="noopener noreferrer"
								>
									{ __( 'WooPay', 'woocommerce-payments' ) }
								</a>
							),
						},
					} ) }
				</span>
			</div>
			<div className="additional-information">
				<div>
					<LockIcon />
				</div>
				<span>
					{ __(
						'Next time you checkout on a WooPay powered store, you’ll receive ' +
							'a code by text message to quickly and securely complete your purchase with your saved information.',
						'woocommerce-payments'
					) }
				</span>
			</div>
		</div>
	);
};

export default AdditionalInformation;
