/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CheckboxControl,
	TextControl,
	BaseControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import BrandingFileUpload from '../../card-readers/settings/file-upload';
import {
	usePlatformCheckoutEnabledSettings,
	usePlatformCheckoutCustomMessage,
	useAccountBrandingLogo,
} from 'wcpay/data';

const CUSTOM_MESSAGE_MAX_LENGTH = 100;

const PlatformCheckoutSettings = () => {
	const [
		getAccountBrandingLogo,
		setAccountBrandingLogo,
	] = useAccountBrandingLogo();

	const [
		isPlatformCheckoutEnabled,
		updateIsPlatformCheckoutEnabled,
	] = usePlatformCheckoutEnabledSettings();

	const [
		platformCheckoutCustomMessage,
		setPlatformCheckoutCustomMessage,
	] = usePlatformCheckoutCustomMessage();

	return (
		<Card className="platform-checkout-settings">
			<CardBody>
				<CheckboxControl
					checked={ isPlatformCheckoutEnabled }
					onChange={ updateIsPlatformCheckoutEnabled }
					label={ __( 'Enable WooPay', 'woocommerce-payments' ) }
					help={ __(
						'When enabled, customers will be able to checkout using WooPay',
						'woocommerce-payments'
					) }
				/>
				<div className="platform-checkout-settings__custom-message-wrapper">
					<h4>{ __( 'Custom message', 'woocommerce-payments' ) }</h4>
					<TextControl
						help={ __(
							'Inform your customers about the return, refund, and exchange policy, or include any other useful message. ' +
								'Note that you can add plain text and links, but not images.',
							'woocommerce-payments'
						) }
						value={ platformCheckoutCustomMessage }
						onChange={ setPlatformCheckoutCustomMessage }
						maxLength={ CUSTOM_MESSAGE_MAX_LENGTH }
					/>
					<span className="input-help-text" aria-hidden="true">
						{ `${ platformCheckoutCustomMessage.length } / ${ CUSTOM_MESSAGE_MAX_LENGTH }` }
					</span>
				</div>
				<div className="platform-checkout-settings__custom-message-wrapper">
					<h4>{ __( 'Store logo', 'woocommerce-payments' ) }</h4>
					<BaseControl
						help={ __(
							'Your business’s logo will be used on orders page.',
							'woocommerce-payments'
						) }
					/>
					<BrandingFileUpload
						fieldKey="branding-logo"
						accept="image/png, image/jpeg"
						disabled={ false }
						help={ __(
							'Upload a .png or .jpg file.',
							'woocommerce-payments'
						) }
						purpose="business_logo"
						fileID={ getAccountBrandingLogo }
						updateFileID={ setAccountBrandingLogo }
					/>
				</div>
			</CardBody>
		</Card>
	);
};

export default PlatformCheckoutSettings;
