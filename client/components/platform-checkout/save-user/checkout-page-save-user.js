/* eslint-disable max-len */
/**
 * External dependencies
 */
import React from 'react';
import { useState } from '@wordpress/element';
import { CheckboxControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import usePlatformCheckoutUser from '../hooks/use-platform-checkout-user';
import AboutPlatformCheckout from './about-platform-checkout';
import AdditionalInformation from './additional-information';
import Agreement from './agreement';
import './style.scss';

const CheckoutPageSaveUser = () => {
	const [ isSaveDetailsChecked, setIsSaveDetailsChecked ] = useState( false );
	const { isRegisteredUser } = usePlatformCheckoutUser();

	if ( isRegisteredUser ) {
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
						value={ '' }
						onChange={ () => {} }
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
