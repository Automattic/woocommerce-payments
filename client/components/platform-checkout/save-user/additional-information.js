/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import LockIcon from '../icons/lock-icon';
import PhoneIcon from '../icons/phone-icon';

const AdditionalInformation = () => {
	return (
		<div>
			<div className="additional-information">
				<PhoneIcon />
				<span>
					{ __(
						"Next time time you checkout, we'll send you a text message to access your saved information.",
						'woocommerce-payments'
					) }
				</span>
			</div>
			<div className="additional-information">
				<LockIcon />
				<span>
					{ __(
						'Your personal details will be encrypted from end to end and payments go through 100% secure servers.',
						'woocommerce-payments'
					) }
				</span>
			</div>
		</div>
	);
};

export default AdditionalInformation;
