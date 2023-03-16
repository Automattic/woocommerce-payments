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
import React, { useEffect, useState } from 'react';

const ContactDetailsSection = ( { setInputsValid } ) => {
	const [ isEmailInputValid, setEmailInputValid ] = useState( true );
	const [ isPhoneInputValid, setPhoneInputValid ] = useState( true );

	useEffect( () => {
		setInputsValid( isEmailInputValid && isPhoneInputValid );
	}, [ isEmailInputValid, isPhoneInputValid, setInputsValid ] );

	return (
		<>
			<h4>
				{ __( 'Customer support contacts', 'woocommerce-payments' ) }
			</h4>
			<SupportEmailInput setInputVallid={ setEmailInputValid } />
			<SupportPhoneInput setInputVallid={ setPhoneInputValid } />
		</>
	);
};

export default ContactDetailsSection;
