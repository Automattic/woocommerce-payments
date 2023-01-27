/** @format */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import SupportPhoneInput from 'wcpay/settings/support-phone-input';
import SupportEmailInput from 'wcpay/settings/support-email-input';

const ContactDetailsSection = ( { setSaveDisabled } ) => {
	return (
		<>
			<h4>
				{ __( 'Customer support contacts', 'woocommerce-payments' ) }
			</h4>
			<SupportEmailInput onErrorMessage={ setSaveDisabled } />
			<SupportPhoneInput onErrorMessage={ setSaveDisabled } />
		</>
	);
};

export default ContactDetailsSection;
