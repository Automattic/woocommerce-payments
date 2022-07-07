/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import usePlatformCheckoutUser from '../hooks/use-platform-checkout-user';
import useSelectedPaymentMethod from '../hooks/use-selected-payment-method';
import AdditionalInformation from './additional-information';
import PhoneNumberInput from 'settings/phone-input';
import Agreement from './agreement';
import { getConfig } from 'utils/checkout';

const CheckoutPageSaveUser = () => {
	const [ isSaveDetailsChecked, setIsSaveDetailsChecked ] = useState( false );
	// eslint-disable-next-line no-unused-vars
	const [ phoneNumber, setPhoneNumber ] = useState( '' );
	const [ isPhoneValid, onPhoneValidationChange ] = useState( null );
	const isRegisteredUser = usePlatformCheckoutUser();
	const {
		isWCPayChosen,
		isNewPaymentTokenChosen,
	} = useSelectedPaymentMethod();

	useEffect( () => {
		const formSubmitButton = document.querySelector(
			'form.woocommerce-checkout button[type="submit"]'
		);

		if ( ! formSubmitButton ) {
			return;
		}

		const updateFormSubmitButton = () => {
			if ( isSaveDetailsChecked && isPhoneValid ) {
				formSubmitButton.removeAttribute( 'disabled' );
			}

			if ( isSaveDetailsChecked && ! isPhoneValid ) {
				formSubmitButton.setAttribute( 'disabled', 'disabled' );
			}
		};

		updateFormSubmitButton();

		return () => {
			// Clean up
			formSubmitButton.removeAttribute( 'disabled' );
		};
	}, [ isPhoneValid, isSaveDetailsChecked ] );

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
			<h3>{ __( 'Remember your details?', 'woocommerce-payments' ) }</h3>
			<span>
				<label htmlFor="save_user_in_platform_checkout">
					<input
						type="checkbox"
						checked={ isSaveDetailsChecked }
						onChange={ () => {
							setIsSaveDetailsChecked( ( v ) => ! v );
							setPhoneNumber( null );
						} }
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
					<span>
						{ __( 'Mobile phone number', 'woocommerce-payments' ) }
					</span>
					<PhoneNumberInput
						value={
							phoneNumber === null
								? document.getElementById( 'billing_phone' )
										?.value
								: phoneNumber
						}
						onValueChange={ setPhoneNumber }
						onValidationChange={ onPhoneValidationChange }
						inputProps={ {
							name:
								'platform_checkout_user_phone_field[no-country-code]',
						} }
					/>
					{ ! isPhoneValid && (
						<p className="error-text">
							{ __(
								'Please enter a valid mobile phone number.',
								'woocommerce-payments'
							) }
						</p>
					) }
					<AdditionalInformation />
					<Agreement />
				</div>
			) }
		</>
	);
};

export default CheckoutPageSaveUser;
