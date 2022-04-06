/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import usePlatformCheckoutUser from '../hooks/use-platform-checkout-user';
import useSelectedPaymentMethod from '../hooks/use-selected-payment-method';
import AdditionalInformation from './additional-information';
import PhoneNumberInput from './phone-number-input';
import Agreement from './agreement';
import { getConfig } from 'utils/checkout';
import './style.scss';

const CheckoutPageSaveUser = () => {
	const [ isSaveDetailsChecked, setIsSaveDetailsChecked ] = useState( false );
	// eslint-disable-next-line no-unused-vars
	const [ phoneNumber, setPhoneNumber ] = useState( '' );
	const isRegisteredUser = usePlatformCheckoutUser();
	const {
		isWCPayChosen,
		isNewPaymentTokenChosen,
	} = useSelectedPaymentMethod();

	if (
		! getConfig( 'forceNetworkSavedCards' ) ||
		! isWCPayChosen ||
		! isNewPaymentTokenChosen ||
		isRegisteredUser
	) {
		return null;
	}

	return (
		<>
			<h3>Remember your details?</h3>
			<span>
				<label htmlFor="save_user_in_platform_checkout">
					<input
						type="checkbox"
						checked={ isSaveDetailsChecked }
						onChange={ ( e ) =>
							setIsSaveDetailsChecked( e.target.checked )
						}
						name="save_user_in_platform_checkout"
						id="save_user_in_platform_checkout"
						value="true"
						className="save-details-checkbox"
						aria-checked={ isSaveDetailsChecked }
					/>
					<span>
						{ __(
							'Save my information for faster checkouts',
							'woocommerce-payments'
						) }
					</span>
				</label>
			</span>
			{ isSaveDetailsChecked && (
				<div
					className="save-details-form form-row place-order"
					data-testid="save-user-form"
				>
					<PhoneNumberInput
						handlePhoneNumberChange={ setPhoneNumber }
					/>
					<AdditionalInformation />
					<Agreement />
				</div>
			) }
		</>
	);
};

export default CheckoutPageSaveUser;
