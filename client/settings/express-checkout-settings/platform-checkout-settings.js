/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl, TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import PlatformCheckoutFileUpload from './file-upload';
import PlatformCheckoutPreview from './platform-checkout-preview';
import {
	usePlatformCheckoutEnabledSettings,
	usePlatformCheckoutCustomMessage,
	usePlatformCheckoutStoreLogo,
	useAccountBusinessName,
} from 'wcpay/data';

const CUSTOM_MESSAGE_MAX_LENGTH = 100;

const PlatformCheckoutSettings = () => {
	const [ accountBusinessName ] = useAccountBusinessName();

	const [
		isPlatformCheckoutEnabled,
		updateIsPlatformCheckoutEnabled,
	] = usePlatformCheckoutEnabledSettings();

	const [
		platformCheckoutCustomMessage,
		setPlatformCheckoutCustomMessage,
	] = usePlatformCheckoutCustomMessage();

	const [
		platformCheckoutStoreLogo,
		setPlatformCheckoutStoreLogo,
	] = usePlatformCheckoutStoreLogo();

	return (
		<div className="platform-checkout-settings">
			<Card>
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
				</CardBody>
			</Card>
			<Card style={ { marginTop: 12 } }>
				<div className="platform-checkout-settings__preview">
					<PlatformCheckoutPreview
						storeName={ accountBusinessName }
						storeLogo={ platformCheckoutStoreLogo }
					></PlatformCheckoutPreview>
					<div className="platform-checkout-settings__preview-fade"></div>
				</div>
				<CardBody>
					<div className="platform-checkout-settings__custom-message-wrapper">
						<h4>
							{ __(
								'Store logo on checkout',
								'woocommerce-payments'
							) }
						</h4>
						<PlatformCheckoutFileUpload
							fieldKey="woopay-store-logo"
							accept="image/png, image/jpeg"
							disabled={ false }
							help={ __(
								'Use a custom logo to WooPay if the one taken from your store doesn’t look right.' +
									' For best results, upload a high-resolution horizontal image with white or transparent background.',
								'woocommerce-payments'
							) }
							purpose="business_logo"
							fileID={ platformCheckoutStoreLogo }
							updateFileID={ setPlatformCheckoutStoreLogo }
						/>
					</div>
					<div className="platform-checkout-settings__custom-message-wrapper">
						<h4>
							{ __( 'Custom message', 'woocommerce-payments' ) }
						</h4>
						<TextControl
							help={ __(
								'Inform your customers about the return, refund, and exchange policy, or include any other useful' +
									' message. Note that you can add plain text and links, but not images.',
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
				</CardBody>
			</Card>
		</div>
	);
};

export default PlatformCheckoutSettings;
