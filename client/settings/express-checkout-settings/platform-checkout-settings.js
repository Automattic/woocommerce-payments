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
import WooPayFileUpload from './file-upload';
import WooPayPreview from './platform-checkout-preview';
import {
	useWooPayEnabledSettings,
	useWooPayCustomMessage,
	useWooPayStoreLogo,
	useWooPayLocations,
} from 'wcpay/data';
import GeneralPaymentRequestButtonSettings from './general-payment-request-button-settings';

const CUSTOM_MESSAGE_MAX_LENGTH = 100;

const WooPaySettings = ( { section } ) => {
	const [
		isWooPayEnabled,
		updateIsWooPayEnabled,
	] = useWooPayEnabledSettings();

	const [
		platformCheckoutCustomMessage,
		setWooPayCustomMessage,
	] = useWooPayCustomMessage();

	const [
		platformCheckoutStoreLogo,
		setWooPayStoreLogo,
	] = useWooPayStoreLogo();

	const [
		platformCheckoutLocations,
		updateWooPayLocations,
	] = useWooPayLocations();

	const makeLocationChangeHandler = ( location ) => ( isChecked ) => {
		if ( isChecked ) {
			updateWooPayLocations( [
				...platformCheckoutLocations,
				location,
			] );
		} else {
			updateWooPayLocations(
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
						checked={ isWooPayEnabled }
						onChange={ updateIsWooPayEnabled }
						label={ __( 'Enable WooPay', 'woocommerce-payments' ) }
						help={ __(
							'When enabled, customers will be able to checkout using WooPay',
							'woocommerce-payments'
						) }
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
								disabled={ ! isWooPayEnabled }
								checked={
									isWooPayEnabled &&
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
								disabled={ ! isWooPayEnabled }
								checked={
									isWooPayEnabled &&
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
								disabled={ ! isWooPayEnabled }
								checked={
									isWooPayEnabled &&
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
						<WooPayPreview
							storeName={ wcSettings.siteTitle }
							storeLogo={ platformCheckoutStoreLogo }
						></WooPayPreview>
						<div className="platform-checkout-settings__preview-fade"></div>
					</div>
					<div className="platform-checkout-settings__custom-message-wrapper">
						<h4>
							{ __(
								'Store logo on checkout',
								'woocommerce-payments'
							) }
						</h4>
						<WooPayFileUpload
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
							updateFileID={ setWooPayStoreLogo }
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
							onChange={ setWooPayCustomMessage }
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

export default WooPaySettings;
