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
import interpolateComponents from '@automattic/interpolate-components';
import LockIconG from 'gridicons/dist/lock';

/**
 * Internal dependencies
 */
import PhoneNumberInput from 'settings/phone-input';
import { getConfig } from 'utils/checkout';
import AdditionalInformation from './additional-information';
import Agreement from './agreement';
import Container from './container';
import useWooPayUser from '../hooks/use-woopay-user';
import useSelectedPaymentMethod from '../hooks/use-selected-payment-method';
import { WC_STORE_CART } from '../../../checkout/constants';
import WooPayIcon from 'assets/images/woopay.svg?asset';
import './style.scss';

const CheckoutPageSaveUser = ( { isBlocksCheckout } ) => {
	const [ isSaveDetailsChecked, setIsSaveDetailsChecked ] = useState( false );
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
	const isRegisteredUser = useWooPayUser();
	const { isWCPayChosen, isNewPaymentTokenChosen } = useSelectedPaymentMethod(
		isBlocksCheckout
	);
	const cart = useDispatch( WC_STORE_CART );
	const viewportWidth = window.document.documentElement.clientWidth;
	const viewportHeight = window.document.documentElement.clientHeight;

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

		// Take out any non-digit characters, except +.
		phoneFieldValue = phoneFieldValue.replace( /[^\d+]*/g, '' );

		if ( ! phoneFieldValue.startsWith( '+' ) ) {
			phoneFieldValue = '+1' + phoneFieldValue;
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
						save_user_in_woopay: isSaveDetailsChecked,
						woopay_source_url: window.location.href,
						woopay_is_blocks: true,
						woopay_viewport: `${ viewportWidth }x${ viewportHeight }`,
						woopay_user_phone_field: {
							full: phoneNumber,
						},
				  };

			extensionCartUpdate( {
				namespace: 'woopay',
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
		[
			isSaveDetailsChecked,
			phoneNumber,
			cart,
			viewportWidth,
			viewportHeight,
		]
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
		<Container isBlocksCheckout={ isBlocksCheckout }>
			<div className="save-details">
				<div className="save-details-header">
					<div
						className={
							isBlocksCheckout
								? 'wc-block-components-checkbox'
								: ''
						}
					>
						<label htmlFor="save_user_in_woopay">
							<input
								type="checkbox"
								checked={ isSaveDetailsChecked }
								onChange={ handleCheckboxClick }
								name="save_user_in_woopay"
								id="save_user_in_woopay"
								value="true"
								className={ `save-details-checkbox ${
									isBlocksCheckout
										? 'wc-block-components-checkbox__input'
										: ''
								}` }
								aria-checked={ isSaveDetailsChecked }
							/>
							{ isBlocksCheckout && (
								<svg
									className="wc-block-components-checkbox__mark"
									aria-hidden="true"
									xmlns="http://www.w3.org/2000/svg"
									viewBox="0 0 24 20"
								>
									<path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" />
								</svg>
							) }
							<span>
								{ isBlocksCheckout
									? __(
											'Save my information for a faster and secure checkout',
											'woocommerce-payments'
									  )
									: __(
											'Save my information for a faster checkout',
											'woocommerce-payments'
									  ) }
							</span>
						</label>
					</div>
					<img
						src={ WooPayIcon }
						className="woopay-logo"
						alt="WooPay"
					/>
					<Icon
						icon={ info }
						size={ 20 }
						className={ `info-icon ${
							isInfoFlyoutVisible ? 'focused' : ''
						}` }
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
							<LockIconG size={ 16 } />
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
											href="https://woocommerce.com/document/woopay-customer-documentation/"
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
						<input
							type="hidden"
							name="woopay_source_url"
							value={ window.location.href }
						/>
						<input
							type="hidden"
							name="woopay_viewport"
							value={ `${ viewportWidth }x${ viewportHeight }` }
						/>
						<PhoneNumberInput
							value={
								phoneNumber === null
									? getPhoneFieldValue()
									: phoneNumber
							}
							onValueChange={ setPhoneNumber }
							onValidationChange={ onPhoneValidationChange }
							inputProps={ {
								name:
									'woopay_user_phone_field[no-country-code]',
							} }
							isBlocksCheckout={ isBlocksCheckout }
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
		</Container>
	);
};

export default CheckoutPageSaveUser;
