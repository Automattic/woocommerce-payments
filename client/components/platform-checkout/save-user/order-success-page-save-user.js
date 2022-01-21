/* eslint-disable max-len */
/**
 * External dependencies
 */
import React, { useState } from 'react';
import { TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import usePlatformCheckoutUser from '../hooks/use-platform-checkout-user';
import AboutPlatformCheckout from './about-platform-checkout';
import AdditionalInformation from './additional-information';
import Agreement from './agreement';
import BeforeRegistrationIcon from '../icons/before-registration-icon';
import AfterRegistrationIcon from '../icons/after-registration-icon copy';
import './style.scss';

const WelcomeMessage = () => {
	return (
		<div className="about-platform-checkout">
			{ interpolateComponents( {
				mixedString: __(
					'{{strong}}Welcome to Platform Checkout!{{/strong}} We’ve securely stored your checkout information. Next time you checkout on a Platform Checkout powered store, you’ll speed through checkout in no time.',
					'woocommerce-payments'
				),
				components: { strong: <b /> },
			} ) }
		</div>
	);
};

const OrderSuccessPageSaveUser = () => {
	const [ phoneNumber, setPhoneNumber ] = useState( '' );
	const [ isRegistrationComplete, setIsRegistrationComplete ] = useState(
		false
	);
	const { isRegisteredUser } = usePlatformCheckoutUser();

	if ( isRegisteredUser ) {
		return null;
	}

	const handleSaveUser = () => {
		setIsRegistrationComplete( true );
	};

	return (
		<div className="platform-checkout-save-new-user-container order-success-page">
			<h3>Check out faster next time</h3>
			{ isRegistrationComplete ? (
				<>
					<AfterRegistrationIcon className="platform-checkout-icon" />
					<WelcomeMessage />
				</>
			) : (
				<>
					<BeforeRegistrationIcon className="platform-checkout-icon" />
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
						<Button isPrimary onClick={ handleSaveUser }>
							{ __( 'Save', 'woocommerce-payments' ) }
						</Button>
					</div>
					<AdditionalInformation />
				</>
			) }
		</div>
	);
};

export default OrderSuccessPageSaveUser;
