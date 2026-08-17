/**
 * External dependencies
 */
import { BaseControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from 'react';

/**
 * Internal dependencies
 */
import {
	useAccountBusinessSupportPhone,
	useGetSavingError,
	useTestModeOnboarding,
} from 'wcpay/data/settings';
import PhoneNumberInput from 'wcpay/settings/phone-input/lazy';
import InlineNotice from 'wcpay/components/inline-notice';
import './styles.scss';

const SupportPhoneInput = ( { setInputValid } ) => {
	const [ supportPhone, setSupportPhone ] = useAccountBusinessSupportPhone();

	let supportPhoneError =
		useGetSavingError()?.data?.details?.account_business_support_phone
			?.message;

	const isTestModeOnboarding = useTestModeOnboarding();
	const isTestPhoneValid =
		isTestModeOnboarding && supportPhone === '+10000000000';

	const [ isPhoneValid, setPhoneValidity ] = useState( true );

	// Empty, never set, and invalid numbers all share one message: the merchant
	// only ever needs to know that a valid number is required.
	if ( supportPhone === '' || ( ! isTestPhoneValid && ! isPhoneValid ) ) {
		supportPhoneError = __(
			'A support phone number is required. Please enter a valid phone number.',
			'woocommerce-payments'
		);
	}

	useEffect( () => {
		if ( setInputValid ) {
			setInputValid( ! supportPhoneError );
		}
	}, [ supportPhoneError, setInputValid ] );

	const labelText = __(
		'Support phone number (required)',
		'woocommerce-payments'
	);
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
							// eslint-disable-next-line max-len
							"This number may appear on customer bank statements and in-person purchase receipts, but not in order emails. Use a number you're comfortable sharing publicly.",
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
