/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl, TextControl } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

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
	usePlatformCheckoutLocations,
} from 'wcpay/data';
import GeneralPaymentRequestButtonSettings from './general-payment-request-button-settings';

const CUSTOM_MESSAGE_MAX_LENGTH = 100;

const PlatformCheckoutSettings = ( { section } ) => {
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

	const [
		platformCheckoutLocations,
		updatePlatformCheckoutLocations,
	] = usePlatformCheckoutLocations();

	const makeLocationChangeHandler = ( location ) => ( isChecked ) => {
		if ( isChecked ) {
			updatePlatformCheckoutLocations( [
				...platformCheckoutLocations,
				location,
			] );
		} else {
			updatePlatformCheckoutLocations(
				platformCheckoutLocations.filter(
					( name ) => name !== location
				)
			);
		}
	};

	return (
		<Card className="platform-checkout-settings">
			{ 'enable' === section && (
				<CardBody>
					<CheckboxControl
						checked={ isPlatformCheckoutEnabled }
						onChange={ updateIsPlatformCheckoutEnabled }
						label={ __( 'Enable WooPay', 'woocommerce-payments' ) }
						help={
							/* eslint-disable jsx-a11y/anchor-has-content */
							isPlatformCheckoutEnabled
								? __(
										'When enabled, customers will be able to checkout using WooPay.',
										'woocommerce-payments'
								  )
								: interpolateComponents( {
										mixedString: __(
											/* eslint-disable-next-line max-len */
											'When enabled, customers will be able to checkout using WooPay. ' +
												'In order to use {{wooPayLink}}WooPay{{/wooPayLink}}, you must agree to our ' +
												'{{tosLink}}WooCommerce Terms of Service{{/tosLink}} ' +
												'and {{privacyLink}}Privacy Policy{{/privacyLink}}. ' +
												'{{trackingLink}}Click here{{/trackingLink}} to learn more about the ' +
												'data you will be sharing and opt-out options.',
											'woocommerce-payments'
										),
										components: {
											wooPayLink: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://woocommerce.com/document/woopay-merchant-documentation/"
												/>
											),
											tosLink: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://wordpress.com/tos/"
												/>
											),
											privacyLink: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://automattic.com/privacy/"
												/>
											),
											trackingLink: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://woocommerce.com/usage-tracking/"
												/>
											),
										},
								  } )
							/* eslint-enable jsx-a11y/anchor-has-content */
						}
					/>
					<h4>
						{ __(
							'Enable WooPay button on selected pages',
							'woocommerce-payments'
						) }
					</h4>
					<ul className="payment-request-settings__location">
						<li>
							<CheckboxControl
								disabled={ ! isPlatformCheckoutEnabled }
								checked={
									isPlatformCheckoutEnabled &&
									platformCheckoutLocations.includes(
										'checkout'
									)
								}
								onChange={ makeLocationChangeHandler(
									'checkout'
								) }
								label={ __(
									'Checkout Page',
									'woocommerce-payments'
								) }
							/>
						</li>
						<li>
							<CheckboxControl
								disabled={ ! isPlatformCheckoutEnabled }
								checked={
									isPlatformCheckoutEnabled &&
									platformCheckoutLocations.includes(
										'product'
									)
								}
								onChange={ makeLocationChangeHandler(
									'product'
								) }
								label={ __(
									'Product Page',
									'woocommerce-payments'
								) }
							/>
						</li>
						<li>
							<CheckboxControl
								disabled={ ! isPlatformCheckoutEnabled }
								checked={
									isPlatformCheckoutEnabled &&
									platformCheckoutLocations.includes( 'cart' )
								}
								onChange={ makeLocationChangeHandler( 'cart' ) }
								label={ __(
									'Cart Page',
									'woocommerce-payments'
								) }
							/>
						</li>
					</ul>
				</CardBody>
			) }

			{ 'appearance' === section && (
				<CardBody style={ { marginTop: 12 } }>
					<div className="platform-checkout-settings__preview">
						<PlatformCheckoutPreview
							storeName={ wcSettings.siteTitle }
							storeLogo={ platformCheckoutStoreLogo }
						></PlatformCheckoutPreview>
						<div className="platform-checkout-settings__preview-fade"></div>
					</div>
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
									' For best results, upload a high-resolution horizontal image' +
									' with white or transparent background.',
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
						<span
							className="input-help-text light-text"
							aria-hidden="true"
						>
							{ `${ platformCheckoutCustomMessage.length } / ${ CUSTOM_MESSAGE_MAX_LENGTH }` }
						</span>
					</div>
				</CardBody>
			) }

			{ 'general' === section && (
				<GeneralPaymentRequestButtonSettings type="woopay" />
			) }
		</Card>
	);
};

export default PlatformCheckoutSettings;
