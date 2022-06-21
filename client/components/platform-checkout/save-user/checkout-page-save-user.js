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
import PhoneNumberInput from './phone-number-input';
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

	// This runs once to populate the phone number state if already present in the DOM.
	useEffect( () => {
		setPhoneNumber(
			document.getElementById( 'billing_phone' )?.value ?? ''
		);
	}, [] );

	useEffect( () => {
		const formSubmitButton = document.querySelector(
			'form.woocommerce-checkout button[type="submit"]'
		);

		if ( ! formSubmitButton ) {
			return;
		}

		const updateFormSubmitButton = () => {
			if ( isPhoneValid ) {
				formSubmitButton.removeAttribute( 'disabled' );
			} else {
				formSubmitButton.setAttribute( 'disabled', 'disabled' );
			}
		};

		updateFormSubmitButton();

		return () => {
			// Clean up
			formSubmitButton.removeAttribute( 'disabled' );
		};
	}, [ isPhoneValid ] );

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
						onChange={ () =>
							setIsSaveDetailsChecked( ( v ) => ! v )
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
					<span>
						{ __( 'Mobile phone number', 'woocommerce-payments' ) }
					</span>
					<PhoneNumberInput
						value={ phoneNumber }
						onValueChange={ setPhoneNumber }
						onValidationChange={ onPhoneValidationChange }
					/>
					<AdditionalInformation />
					<Agreement />
				</div>
			) }
		</>
	);
};

export default CheckoutPageSaveUser;
