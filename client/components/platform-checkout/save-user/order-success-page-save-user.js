/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useState } from 'react';
import { TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import usePlatformCheckoutUser from '../hooks/use-platform-checkout-user';
import AboutPlatformCheckout from './about-platform-checkout';
import AdditionalInformation from './additional-information';
import Agreement from './agreement';
import './style.scss';

const OrderSuccessPageSaveUser = () => {
	const [ phoneNumber, setPhoneNumber ] = useState( '' );
	const { isRegisteredUser } = usePlatformCheckoutUser();

	if ( isRegisteredUser ) {
		return null;
	}

	return (
		<div className="platform-checkout-save-new-user-container order-success-page">
			<h3>Check out faster next time</h3>
			<AboutPlatformCheckout />
			<Agreement isSaving={ true } />
			<div className="save-mobile-number">
				<TextControl
					type="text"
					label={ __(
						'Confirm your mobile number',
						'woocommerce-payments'
					) }
					value={ phoneNumber }
					onChange={ setPhoneNumber }
				/>
				<Button isPrimary onClick={ () => {} }>
					{ __( 'Save', 'woocommerce-payments' ) }
				</Button>
			</div>
			<AdditionalInformation />
		</div>
	);
};

export default OrderSuccessPageSaveUser;
