/** @format */
/**
 * External dependencies
 */
import React from 'react';
import classNames from 'classnames';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CheckboxControl,
	TextareaControl,
	ExternalLink,
} from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import WooPayFileUpload from './file-upload';
import WooPayPreview from './woopay-preview';
import {
	useWooPayEnabledSettings,
	useWooPayCustomMessage,
	useWooPayStoreLogo,
	useWooPayLocations,
	useWooPayShowIncompatibilityNotice,
} from 'wcpay/data';
import GeneralPaymentRequestButtonSettings from './general-payment-request-button-settings';
import WooPayIncompatibilityNotice from '../settings-warnings/incompatibility-notice';

const WooPaySettings = ( { section } ) => {
	const [
		isWooPayEnabled,
		updateIsWooPayEnabled,
	] = useWooPayEnabledSettings();

	const [
		woopayCustomMessage,
		setWooPayCustomMessage,
	] = useWooPayCustomMessage();

	const [ woopayStoreLogo, setWooPayStoreLogo ] = useWooPayStoreLogo();

	const [ woopayLocations, updateWooPayLocations ] = useWooPayLocations();

	const makeLocationChangeHandler = ( location ) => ( isChecked ) => {
		if ( isChecked ) {
			updateWooPayLocations( [ ...woopayLocations, location ] );
		} else {
			updateWooPayLocations(
				woopayLocations.filter( ( name ) => name !== location )
			);
		}
	};

	const showIncompatibilityNotice = useWooPayShowIncompatibilityNotice();

	return (
		<Card
			className={ classNames( {
				'woopay-settings': true,
				'woopay-settings--appearance': section === 'appearance',
			} ) }
		>
			{ section === 'enable' && (
				<CardBody>
					{ showIncompatibilityNotice && (
						<WooPayIncompatibilityNotice />
					) }
					<CheckboxControl
						checked={ isWooPayEnabled }
						onChange={ updateIsWooPayEnabled }
						label={ __( 'Enable WooPay', 'woocommerce-payments' ) }
						help={
							/* eslint-disable jsx-a11y/anchor-has-content */
							isWooPayEnabled
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
								disabled={ ! isWooPayEnabled }
								checked={
									isWooPayEnabled &&
									woopayLocations.includes( 'checkout' )
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
									woopayLocations.includes( 'product' )
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
									woopayLocations.includes( 'cart' )
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

			{ section === 'appearance' && (
				<CardBody className="woopay-settings__appearance-card-settings">
					<div className="woopay-settings__custom-message-wrapper">
						<h4>
							{ __( 'Checkout logo', 'woocommerce-payments' ) }
						</h4>
						<WooPayFileUpload
							fieldKey="woopay-store-logo"
							accept="image/png, image/jpeg"
							disabled={ false }
							help={ __(
								'Upload a custom logo. Upload a horizontal image with a white' +
									' or transparent background for best results. Use a PNG or JPG' +
									' image format. Recommended width: 512 pixels minimum.',
								'woocommerce-payments'
							) }
							purpose="business_logo"
							fileID={ woopayStoreLogo }
							updateFileID={ setWooPayStoreLogo }
						/>
					</div>
					<div className="woopay-settings__custom-message-wrapper">
						<h4>
							{ __(
								'Policies and custom text',
								'woocommerce-payments'
							) }
						</h4>
						<TextareaControl
							help={ interpolateComponents( {
								mixedString: __(
									'Override the default {{privacyLink}}privacy policy{{/privacyLink}}' +
										' and {{termsLink}}terms of service{{/termsLink}},' +
										' or add custom text to WooPay checkout. {{learnMoreLink}}Learn more{{/learnMoreLink}}.',
									'woocommerce-payments'
								),
								// prettier-ignore
								components: {
									/* eslint-disable prettier/prettier */
									privacyLink: window.wcSettings?.storePages?.privacy?.permalink ?
										<Link href={ window.wcSettings.storePages.privacy.permalink } type="external" /> :
										<span />,
									termsLink: window.wcSettings?.storePages?.terms?.permalink ?
										<Link href={ window.wcSettings.storePages.terms.permalink } type="external" /> :
										<span />,
									/* eslint-enable prettier/prettier */
									learnMoreLink: (
										// eslint-disable-next-line max-len
										<ExternalLink href="https://woocommerce.com/document/woopay-merchant-documentation/#checkout-appearance" />
									),
								}
							} ) }
							value={ woopayCustomMessage }
							onChange={ setWooPayCustomMessage }
						/>
					</div>
				</CardBody>
			) }

			{ section === 'appearance' && (
				<CardBody className="woopay-settings__appearance-card-preview">
					<div className="woopay-settings__preview">
						<h4>
							{ __(
								'Preview of checkout',
								'woocommerce-payments'
							) }
						</h4>
						<WooPayPreview
							storeName={ wcSettings.siteTitle }
							storeLogo={ woopayStoreLogo }
							customMessage={ woopayCustomMessage }
						></WooPayPreview>
					</div>
				</CardBody>
			) }

			{ section === 'general' && (
				<GeneralPaymentRequestButtonSettings type="woopay" />
			) }
		</Card>
	);
};

export default WooPaySettings;
