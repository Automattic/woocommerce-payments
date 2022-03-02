/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useState } from 'react';
import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import usePlatformCheckoutUser from '../hooks/use-platform-checkout-user';
import useSelectedPaymentMethod from '../hooks/use-selected-payment-method';
import AboutPlatformCheckout from './about-platform-checkout';
import AdditionalInformation from './additional-information';
import PhoneNumberInput from './phone-number-input';
import Agreement from './agreement';
import './style.scss';

const CheckoutPageSaveUser = () => {
	const [ isSaveDetailsChecked, setIsSaveDetailsChecked ] = useState( false );
	// eslint-disable-next-line no-unused-vars
	const [ phoneNumber, setPhoneNumber ] = useState( '' );
	const isRegisteredUser = usePlatformCheckoutUser();
	const { isWCPayChosen } = useSelectedPaymentMethod();

	if ( ! isWCPayChosen || isRegisteredUser ) {
		return null;
	}

	return (
		<>
			<h3>Remember your details?</h3>
			<CheckboxControl
				checked={ isSaveDetailsChecked }
				onChange={ setIsSaveDetailsChecked }
				name="save_user_in_platform_checkout"
				label={ __(
					'Save my information for faster checkouts',
					'woocommerce-payments'
				) }
			/>
			{ isSaveDetailsChecked && (
				<div
					className="save-details-form place-order"
					data-testid="save-user-form"
				>
					<AboutPlatformCheckout />
					<PhoneNumberInput
						handlePhoneNumberChange={ setPhoneNumber }
					/>
					<AdditionalInformation />
					<Agreement />
					<div className="line"></div>
				</div>
			) }
		</>
	);
};

export default CheckoutPageSaveUser;
