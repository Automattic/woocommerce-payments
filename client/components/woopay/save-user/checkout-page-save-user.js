/* eslint-disable max-len */
/* global jQuery */
/**
 * External dependencies
 */
import React, { useEffect, useState, useCallback } from 'react';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	extensionCartUpdate,
	ValidationInputError,
} from '@woocommerce/blocks-checkout'; // eslint-disable-line import/no-unresolved
import {
	VALIDATION_STORE_KEY,
	CHECKOUT_STORE_KEY,
} from '@woocommerce/block-data'; // eslint-disable-line import/no-unresolved

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
import { recordUserEvent } from 'tracks';
import './style.scss';
import { compare } from 'compare-versions';

const CheckoutPageSaveUser = ( { isBlocksCheckout } ) => {
	const errorId = 'invalid-woopay-phone-number';

	const { setValidationErrors, clearValidationError } = useDispatch(
		VALIDATION_STORE_KEY
	);

	const [ isSaveDetailsChecked, setIsSaveDetailsChecked ] = useState(
		window.woopayCheckout?.PRE_CHECK_SAVE_MY_INFO || false
	);
	const [ phoneNumber, setPhoneNumber ] = useState( '' );
	const [ isPhoneValid, onPhoneValidationChange ] = useState( null );
	const [ userDataSent, setUserDataSent ] = useState( false );

	const checkoutIsProcessing = useSelect( ( select ) =>
		select( CHECKOUT_STORE_KEY ).isProcessing()
	);

	const isRegisteredUser = useWooPayUser();
	const { isWCPayChosen, isNewPaymentTokenChosen } = useSelectedPaymentMethod(
		isBlocksCheckout
	);
	const viewportWidth = window.document.documentElement.clientWidth;
	const viewportHeight = window.document.documentElement.clientHeight;
	const wooCommerceVersionString = window.wcSettings?.wcVersion;
	const wcVersionGreaterThan91 = compare(
		wooCommerceVersionString,
		'9.1',
		'>='
	);

	useEffect( () => {
		if ( ! isBlocksCheckout ) {
			return;
		}

		const rememberMe = document.querySelector( '#remember-me' );

		if ( ! rememberMe ) {
			return;
		}

		if ( checkoutIsProcessing ) {
			rememberMe.classList.add(
				'wc-block-components-checkout-step--disabled'
			);
			rememberMe.setAttribute( 'disabled', 'disabled' );

			return;
		}

		rememberMe.classList.remove(
			'wc-block-components-checkout-step--disabled'
		);
		rememberMe.removeAttribute( 'disabled', 'disabled' );
	}, [ checkoutIsProcessing, isBlocksCheckout ] );

	const getPhoneFieldValue = () => {
		let phoneFieldValue = '';
		if ( isBlocksCheckout ) {
			phoneFieldValue =
				document.getElementById( 'phone' )?.value ||
				document.getElementById( 'shipping-phone' )?.value ||
				// in case of virtual products, the shipping phone is not available. So we also need to check the billing phone.
				document.getElementById( 'billing-phone' )?.value ||
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
			const data = shouldClearData
				? {}
				: {
						save_user_in_woopay: isSaveDetailsChecked,
						woopay_source_url:
							wcSettings?.storePages?.checkout?.permalink,
						woopay_is_blocks: true,
						woopay_viewport: `${ viewportWidth }x${ viewportHeight }`,
						woopay_user_phone_field: {
							full: phoneNumber,
						},
				  };

			extensionCartUpdate( {
				namespace: 'woopay',
				data: data,
			} )?.then( () => {
				setUserDataSent( ! shouldClearData );
			} );
		},
		[ isSaveDetailsChecked, phoneNumber, viewportWidth, viewportHeight ]
	);

	const handleCountryDropdownClick = useCallback( () => {
		recordUserEvent( 'checkout_woopay_save_my_info_country_click' );
	}, [] );

	const handleCheckboxClick = ( e ) => {
		const isChecked = e.target.checked;
		if ( isChecked ) {
			setPhoneNumber( getPhoneFieldValue() );
		} else {
			setPhoneNumber( '' );
			if ( isBlocksCheckout ) {
				sendExtensionData( true );
			}
		}
		setIsSaveDetailsChecked( isChecked );

		recordUserEvent( 'checkout_save_my_info_click', {
			status: isChecked ? 'checked' : 'unchecked',
		} );
	};

	useEffect( () => {
		// Record Tracks event when the mobile number is entered.
		if ( isPhoneValid ) {
			recordUserEvent( 'checkout_woopay_save_my_info_mobile_enter' );
		}
	}, [ isPhoneValid ] );

	useEffect( () => {
		const checkoutForm = jQuery( 'form.woocommerce-checkout' );

		checkoutForm.on( 'checkout_place_order', function () {
			jQuery( '#validate-error-invalid-woopay-phone-number' ).show();
		} );
	}, [] );

	const updatePhoneNumberValidationError = useCallback( () => {
		if ( ! isSaveDetailsChecked ) {
			clearValidationError( errorId );
			if ( isPhoneValid !== null ) {
				onPhoneValidationChange( null );
			}
			return;
		}

		if ( isSaveDetailsChecked && isPhoneValid ) {
			clearValidationError( errorId );

			// Set extension data if checkbox is selected and phone number is valid in blocks checkout.
			if ( isBlocksCheckout ) {
				sendExtensionData( false );
			}
			return;
		}

		if ( isSaveDetailsChecked && ! isPhoneValid ) {
			setValidationErrors( {
				[ errorId ]: {
					message: __(
						'Please enter a valid mobile phone number.',
						'woocommerce-payments'
					),
					// Hides errors when the number has not been typed yet but shows when trying to place the order.
					hidden: isPhoneValid === null,
				},
			} );
		}
	}, [
		clearValidationError,
		isBlocksCheckout,
		isPhoneValid,
		isSaveDetailsChecked,
		sendExtensionData,
		setValidationErrors,
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
		clearValidationError( errorId );
		return null;
	}

	updatePhoneNumberValidationError();

	return (
		<Container
			isBlocksCheckout={ isBlocksCheckout }
			wcVersionGreaterThan91={ wcVersionGreaterThan91 }
		>
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
									wcVersionGreaterThan91
										? 'without-margin-right'
										: ''
								} ${
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
								{ __(
									'Securely save my information for 1-click checkout',
									'woocommerce-payments'
								) }
							</span>
						</label>
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
							value={
								wcSettings?.storePages?.checkout?.permalink
							}
						/>
						<input
							type="hidden"
							name="woopay_viewport"
							value={ `${ viewportWidth }x${ viewportHeight }` }
						/>
						<div className={ isPhoneValid ? '' : 'has-error' }>
							<PhoneNumberInput
								value={ phoneNumber }
								onValueChange={ setPhoneNumber }
								onValidationChange={ onPhoneValidationChange }
								onCountryDropdownClick={
									handleCountryDropdownClick
								}
								inputProps={ {
									name:
										'woopay_user_phone_field[no-country-code]',
								} }
								isBlocksCheckout={ isBlocksCheckout }
							/>
						</div>
						{ isBlocksCheckout && (
							<ValidationInputError
								elementId={ errorId }
								propertyName={ errorId }
							/>
						) }
						{ ! isBlocksCheckout && ! isPhoneValid && (
							<p
								id="validate-error-invalid-woopay-phone-number"
								hidden={ isPhoneValid !== false }
							>
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
