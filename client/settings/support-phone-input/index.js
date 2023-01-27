/**
 * External dependencies
 */
import { BaseControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from 'react';

/**
 * Internal dependencies
 */
import { useAccountBusinessSupportPhone, useGetSavingError } from 'wcpay/data';
import PhoneNumberInput from 'wcpay/settings/phone-input';

const SupportPhoneInput = ( { onErrorMessage } ) => {
	const [ supportPhone, setSupportPhone ] = useAccountBusinessSupportPhone();

	let supportPhoneError = useGetSavingError()?.data?.details
		?.account_business_support_phone?.message;

	if ( '' === supportPhone ) {
		supportPhoneError = __(
			'Support phone number cannot be empty, please specify.',
			'woocommerce-payments'
		);
	}
	const [ isPhoneValid, setPhoneValidity ] = useState( true );

	if ( ! isPhoneValid ) {
		supportPhoneError = __(
			'Please enter a valid mobile phone number.',
			'woocommerce-payments'
		);
	}

	useEffect( () => {
		if ( onErrorMessage ) {
			onErrorMessage( !! supportPhoneError );
		}
	}, [ supportPhoneError, onErrorMessage ] );

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
