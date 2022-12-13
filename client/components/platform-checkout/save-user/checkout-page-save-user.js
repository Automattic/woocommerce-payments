/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useEffect, useState, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
// eslint-disable-next-line import/no-unresolved
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';
import { Icon, info } from '@wordpress/icons';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import usePlatformCheckoutUser from '../hooks/use-platform-checkout-user';
import useSelectedPaymentMethod from '../hooks/use-selected-payment-method';
import AdditionalInformation from './additional-information';
import PhoneNumberInput from 'settings/phone-input';
import Agreement from './agreement';
import { getConfig } from 'utils/checkout';
import { WC_STORE_CART } from '../../../checkout/constants';
import WooPayIcon from '../../../../assets/images/woopay.svg';
import LockIcon from '../icons/lock';
import './style.scss';

const CheckoutPageSaveUser = ( { isBlocksCheckout } ) => {
	const [ isSaveDetailsChecked, setIsSaveDetailsChecked ] = useState( false );
	// eslint-disable-next-line no-unused-vars
	const [ phoneNumber, setPhoneNumber ] = useState( '' );
	const [ isPhoneValid, onPhoneValidationChange ] = useState( null );
	const [ userDataSent, setUserDataSent ] = useState( false );
	const [ isInfoFlyoutVisible, setIsInfoFlyoutVisible ] = useState( false );
	const setInfoFlyoutVisible = useCallback(
		() => setIsInfoFlyoutVisible( true ),
		[]
	);
	const setInfoFlyoutNotVisible = useCallback(
		() => setIsInfoFlyoutVisible( false ),
		[]
	);
	const isRegisteredUser = usePlatformCheckoutUser();
	const { isWCPayChosen, isNewPaymentTokenChosen } = useSelectedPaymentMethod(
		isBlocksCheckout
	);
	const cart = useDispatch( WC_STORE_CART );

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

	const sendExtensionData = useCallback(
		( shouldClearData = false ) => {
			const shippingPhone = document.getElementById( 'shipping-phone' )
				?.value;
			const billingPhone = document.getElementById( 'phone' )?.value;
			const data = shouldClearData
				? {}
				: {
						save_user_in_platform_checkout: isSaveDetailsChecked,
						platform_checkout_user_phone_field: {
							full: phoneNumber,
						},
				  };

			extensionCartUpdate( {
				namespace: 'platform-checkout',
				data: data,
			} ).then( () => {
				setUserDataSent( ! shouldClearData );
				// Cart returned from `extensionCartUpdate` clears these as these fields are not sent to backend by blocks when added.
				// Setting them explicitly here to the previous user input.
				cart.setShippingAddress( {
					phone: shippingPhone,
				} );
				cart.setBillingAddress( {
					phone: billingPhone,
				} );
			} );
		},
		[ isSaveDetailsChecked, phoneNumber, cart ]
	);

	const handleCheckboxClick = ( e ) => {
		const isChecked = e.target.checked;
		if ( isChecked ) {
			setPhoneNumber( getPhoneFieldValue() );
		} else {
			setPhoneNumber( null );
			if ( isBlocksCheckout ) {
				sendExtensionData( true );
			}
		}
		setIsSaveDetailsChecked( isChecked );
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

				// Set extension data if checkbox is selected and phone number is valid in blocks checkout.
				if ( isBlocksCheckout ) {
					sendExtensionData( false );
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
	}, [
		isBlocksCheckout,
		isPhoneValid,
		isSaveDetailsChecked,
		sendExtensionData,
	] );

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
			sendExtensionData( true );
		}
		return null;
	}

	return (
		<div className="save-details">
			<div className="save-details-header">
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
							'Save my information for a faster and secure checkout',
							'woocommerce-payments'
						) }
					</span>
				</label>
				<img src={ WooPayIcon } alt="WooPay" className="woopay-logo" />
				<Icon
					icon={ info }
					size={ 36 }
					className={
						isInfoFlyoutVisible ? 'focused info-icon' : 'info-icon'
					}
					onMouseOver={ setInfoFlyoutVisible }
					onMouseOut={ setInfoFlyoutNotVisible }
				/>
				<div
					className="save-details-flyout"
					onMouseOver={ setInfoFlyoutVisible }
					onFocus={ setInfoFlyoutVisible }
					onMouseOut={ setInfoFlyoutNotVisible }
					onBlur={ setInfoFlyoutNotVisible }
				>
					<div>
						<LockIcon />
					</div>
					<span>
						{ interpolateComponents( {
							mixedString: __(
								'We use {{woopayBold/}} to securely store your information in this WooCommerce store and others. ' +
									"Next time at checkout, we'll send you a code by SMS to authenticate your purchase. {{learnMore/}}",
								'woocommerce-payments'
							),
							components: {
								woopayBold: <b>WooPay</b>,
								learnMore: (
									<a
										target="_blank"
										href="https://automattic.com"
										rel="noopener noreferrer"
									>
										{ __(
											'Learn more',
											'woocommerce-payments'
										) }
									</a>
								),
							},
						} ) }
					</span>
				</div>
			</div>
			{ isSaveDetailsChecked && (
				<div
					className="save-details-form form-row"
					data-testid="save-user-form"
				>
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
		</div>
	);
};

export default CheckoutPageSaveUser;
