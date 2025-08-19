/**
 * External dependencies
 */
import { BaseControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from 'react';

/**
 * Internal dependencies
 */
import {
	useAccountBusinessSupportPhone,
	useGetSavingError,
	useTestModeOnboarding,
} from 'wcpay/data';
import PhoneNumberInput from 'wcpay/settings/phone-input';
import InlineNotice from 'wcpay/components/inline-notice';
import './styles.scss';

const SupportPhoneInput = ( { setInputVallid } ) => {
	const [ supportPhone, setSupportPhone ] = useAccountBusinessSupportPhone();

	let supportPhoneError = useGetSavingError()?.data?.details
		?.account_business_support_phone?.message;

	const currentPhone = useRef( supportPhone ).current;
	const isEmptyPhoneValid = supportPhone === '' && currentPhone === '';
	const isTestModeOnboarding = useTestModeOnboarding();
	const isTestPhoneValid =
		isTestModeOnboarding && supportPhone === '+10000000000';

	const [ isPhoneValid, setPhoneValidity ] = useState( true );
	if ( supportPhone === '' ) {
		supportPhoneError = __(
			'Support phone number cannot be empty.',
			'woocommerce-payments'
		);
	}
	if ( ! isTestPhoneValid && ! isPhoneValid && ! isEmptyPhoneValid ) {
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

	const labelText = __( 'Support phone number', 'woocommerce-payments' );
	return (
		<>
			{ supportPhoneError && (
				<InlineNotice status="error" isDismissible={ false }>
					<span>{ supportPhoneError }</span>
				</InlineNotice>
			) }
			<BaseControl
				className="settings__account-business-support-phone-input no-top-margin"
				help={
					<>
						{ __(
							'This may be visible on receipts, invoices, and automated emails from your store.',
							'woocommerce-payments'
						) }
						{ isTestModeOnboarding && (
							<>
								<br />
								{ __(
									'(+1 0000000000 can be used for test accounts)',
									'woocommerce-payments'
								) }
							</>
						) }
					</>
				}
				label={ labelText }
				id="account-business-support-phone-input"
				__nextHasNoMarginBottom
			>
				<PhoneNumberInput
					id="account-business-support-phone-input"
					onValueChange={ setSupportPhone }
					value={ supportPhone }
					onValidationChange={ setPhoneValidity }
					inputProps={ {
						ariaLabel: labelText,
					} }
				/>
			</BaseControl>
		</>
	);
};

export default SupportPhoneInput;
