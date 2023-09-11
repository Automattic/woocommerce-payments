/**
 * External dependencies
 */
import { BaseControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from 'react';

/**
 * Internal dependencies
 */
import { useAccountBusinessSupportPhone, useGetSavingError } from 'wcpay/data';
import PhoneNumberInput from 'wcpay/settings/phone-input';

const SupportPhoneInput = ( { setInputVallid } ) => {
	const [ supportPhone, setSupportPhone ] = useAccountBusinessSupportPhone();

	let supportPhoneError = useGetSavingError()?.data?.details
		?.account_business_support_phone?.message;

	const currentPhone = useRef( supportPhone ).current;
	const isEmptyPhoneValid = supportPhone === '' && currentPhone === '';

	const [ isPhoneValid, setPhoneValidity ] = useState( true );
	if ( ! isPhoneValid && ! isEmptyPhoneValid ) {
		supportPhoneError = __(
			'Please enter a valid phone number.',
			'woocommerce-payments'
		);
	}

	if ( supportPhone === '' && currentPhone !== '' ) {
		supportPhoneError = __(
			'Support phone number cannot be empty once it has been set before, please specify.',
			'woocommerce-payments'
		);
	}

	useEffect( () => {
		if ( setInputVallid ) {
			setInputVallid( ! supportPhoneError );
		}
	}, [ supportPhoneError, setInputVallid ] );

	return (
		<>
			{ supportPhoneError && (
				<Notice status="error" isDismissible={ false }>
					<span>{ supportPhoneError }</span>
				</Notice>
			) }
			<BaseControl
				className="settings__account-business-support-phone-input no-top-margin"
				help={ __(
					'This may be visible on receipts, invoices, and automated emails from your store.',
					'woocommerce-payments'
				) }
				label={ __( 'Support phone number', 'woocommerce-payments' ) }
				id="account-business-support-phone-input"
			>
				<PhoneNumberInput
					onValueChange={ setSupportPhone }
					value={ supportPhone }
					onValidationChange={ setPhoneValidity }
					inputProps={ {
						ariaLabel: __(
							'Support phone number',
							'woocommerce-payments'
						),
					} }
				/>
			</BaseControl>
		</>
	);
};

export default SupportPhoneInput;
