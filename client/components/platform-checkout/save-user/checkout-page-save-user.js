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
// eslint-disable-next-line import/no-unresolved
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';

const CheckoutPageSaveUser = ( { isBlocksCheckout } ) => {
	const [ isSaveDetailsChecked, setIsSaveDetailsChecked ] = useState( false );
	// eslint-disable-next-line no-unused-vars
	const [ phoneNumber, setPhoneNumber ] = useState( '' );
	const [ isPhoneValid, onPhoneValidationChange ] = useState( null );
	const [ userDataSent, setUserDataSent ] = useState( false );
	const isRegisteredUser = usePlatformCheckoutUser();
	const { isWCPayChosen, isNewPaymentTokenChosen } = useSelectedPaymentMethod(
		isBlocksCheckout
	);

	const getPhoneFieldValue = () => {
		let phoneFieldValue = '';
		if ( isBlocksCheckout ) {
			phoneFieldValue =
				document.getElementById( 'phone' )?.value ||
				document.getElementById( 'shipping-phone' )?.value ||
				'';
		} else {
			// for classic checkout.
			phoneFieldValue =
				document.getElementById( 'billing_phone' )?.value || '';
		}

		return phoneFieldValue;
	};

	const handleCheckboxClick = () => {
		if ( isSaveDetailsChecked ) {
			setPhoneNumber( null );
			if ( isBlocksCheckout ) {
				extensionCartUpdate( {
					namespace: 'platform-checkout',
					data: {},
				} ).then( () => {
					setUserDataSent( false );
				} );
			}
		} else {
			setPhoneNumber( getPhoneFieldValue() );
		}
		setIsSaveDetailsChecked( ( v ) => ! v );
	};

	useEffect( () => {
		const formSubmitButton = isBlocksCheckout
			? document.querySelector(
					'button.wc-block-components-checkout-place-order-button'
			  )
			: document.querySelector(
					'form.woocommerce-checkout button[type="submit"]'
			  );

		if ( ! formSubmitButton ) {
			return;
		}

		const updateFormSubmitButton = () => {
			if ( isSaveDetailsChecked && isPhoneValid ) {
				formSubmitButton.removeAttribute( 'disabled' );

				if ( isBlocksCheckout ) {
					// Set extension data if checkbox is selected and phone number is valid.
					extensionCartUpdate( {
						namespace: 'platform-checkout',
						data: {
							save_user_in_platform_checkout: isSaveDetailsChecked,
							platform_checkout_user_phone_field: {
								full: phoneNumber,
							},
						},
					} ).then( () => {
						setUserDataSent( true );
					} );
				}
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
	}, [ isBlocksCheckout, isPhoneValid, isSaveDetailsChecked, phoneNumber ] );

	// In classic checkout the saved tokens are under WCPay, so we need to check if new token is selected or not,
	// under WCPay. For blocks checkout considering isWCPayChosen is enough.
	const isWCPayWithNewTokenChosen = isBlocksCheckout
		? isWCPayChosen
		: isWCPayChosen && isNewPaymentTokenChosen;

	if (
		! getConfig( 'forceNetworkSavedCards' ) ||
		! isWCPayWithNewTokenChosen ||
		isRegisteredUser
	) {
		// Clicking the place order button sets the extension data in backend. If user changes the payment method
		// due to an error, we need to clear the extension data in backend.
		if ( isBlocksCheckout && userDataSent ) {
			extensionCartUpdate( {
				namespace: 'platform-checkout',
				data: {},
			} ).then( () => {
				setUserDataSent( false );
			} );
		}
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
						onChange={ handleCheckboxClick }
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
							null === phoneNumber
								? getPhoneFieldValue()
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
