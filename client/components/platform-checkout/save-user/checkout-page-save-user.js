/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useState } from 'react';
import { CheckboxControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import usePlatformCheckoutUser from '../hooks/use-platform-checkout-user';
import useSelectedPaymentMethod from '../hooks/use-selected-payment-method';
import AboutPlatformCheckout from './about-platform-checkout';
import AdditionalInformation from './additional-information';
import Agreement from './agreement';
import './style.scss';

const CheckoutPageSaveUser = () => {
	const [ isSaveDetailsChecked, setIsSaveDetailsChecked ] = useState( false );
	const [ phoneNumber, setPhoneNumber ] = useState( '' );
	const { isRegisteredUser } = usePlatformCheckoutUser();
	const { isWCPayChosen } = useSelectedPaymentMethod();

	if ( ! isWCPayChosen || isRegisteredUser ) {
		return null;
	}

	return (
		<div className="platform-checkout-save-new-user-container">
			<h3>Remember your details?</h3>
			<CheckboxControl
				checked={ isSaveDetailsChecked }
				onChange={ setIsSaveDetailsChecked }
				label={ __(
					'Save my information for faster checkouts',
					'woocommerce-payments'
				) }
			/>
			{ isSaveDetailsChecked && (
				<div className="save-details-form">
					<AboutPlatformCheckout />
					<TextControl
						type="text"
						label={ __(
							'Mobile phone number',
							'woocommerce-payments'
						) }
						value={ phoneNumber }
						onChange={ setPhoneNumber }
					/>
					<AdditionalInformation />
					<Agreement />
					<div className="line"></div>
				</div>
			) }
		</div>
	);
};

export default CheckoutPageSaveUser;
