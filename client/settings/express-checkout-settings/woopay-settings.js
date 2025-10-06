/** @format */
/**
 * External dependencies
 */
import React from 'react';
import clsx from 'clsx';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import {
	Card,
	CheckboxControl,
	TextareaControl,
	ExternalLink,
	BaseControl,
} from '@wordpress/components';
import CardBody from '../card-body';
import WooPayFileUpload from './file-upload';
import WooPayPreview from './woopay-preview';
import {
	useWooPayEnabledSettings,
	useWooPayCustomMessage,
	useWooPayStoreLogo,
	useWooPayLocations,
	useWooPayShowIncompatibilityNotice,
	useWooPayGlobalThemeSupportEnabledSettings,
} from 'wcpay/data';
import GeneralPaymentRequestButtonSettings from './general-payment-request-button-settings';
import { WooPayIncompatibilityNotice } from '../settings-warnings/incompatibility-notice';

const WooPaySettings = ( { section } ) => {
	const [
		isWooPayEnabled,
		updateIsWooPayEnabled,
	] = useWooPayEnabledSettings();

	const [
		woopayCustomMessage,
		setWooPayCustomMessage,
	] = useWooPayCustomMessage();

	const [
		isWooPayGlobalThemeSupportEnabled,
		updateIsWooPayGlobalThemeSupportEnabled,
	] = useWooPayGlobalThemeSupportEnabledSettings();

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
			className={ clsx( {
				'woopay-settings': true,
				'woopay-settings--appearance': section === 'appearance',
			} ) }
		>
			{ section === 'enable' && (
				<CardBody className="wcpay-card-body">
					{ showIncompatibilityNotice && (
						<WooPayIncompatibilityNotice />
					) }
					<div className="wcpay-woopay-settings__enable">
						<CheckboxControl
							checked={ isWooPayEnabled }
							onChange={ updateIsWooPayEnabled }
							label={ __(
								'Enable WooPay',
								'woocommerce-payments'
							) }
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
							__nextHasNoMarginBottom
						/>
						{ /* eslint-disable-next-line @wordpress/no-base-control-with-label-without-id */ }
						<BaseControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						>
							<ul className="payment-request-settings__location">
								<li>
									<CheckboxControl
										disabled={ ! isWooPayEnabled }
										checked={
											isWooPayEnabled &&
											woopayLocations.includes(
												'product'
											)
										}
										onChange={ makeLocationChangeHandler(
											'product'
										) }
										label={ __(
											'Show on product page',
											'woocommerce-payments'
										) }
										__nextHasNoMarginBottom
									/>
								</li>
								<li>
									<CheckboxControl
										disabled={ ! isWooPayEnabled }
										checked={
											isWooPayEnabled &&
											woopayLocations.includes( 'cart' )
										}
										onChange={ makeLocationChangeHandler(
											'cart'
										) }
										label={ __(
											'Show on cart page',
											'woocommerce-payments'
										) }
										__nextHasNoMarginBottom
									/>
								</li>
								<li>
									<CheckboxControl
										disabled={ ! isWooPayEnabled }
										checked={
											isWooPayEnabled &&
											woopayLocations.includes(
												'checkout'
											)
										}
										onChange={ makeLocationChangeHandler(
											'checkout'
										) }
										label={ __(
											'Show on checkout page',
											'woocommerce-payments'
										) }
										__nextHasNoMarginBottom
									/>
								</li>
							</ul>
						</BaseControl>
					</div>
				</CardBody>
			) }

			{ section === 'appearance' && (
				<CardBody className="wcpay-card-body woopay-settings__appearance-card-settings">
					<WooPayFileUpload
						fieldKey="woopay-store-logo"
						label={ __( 'Checkout logo', 'woocommerce-payments' ) }
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
					{ wcpaySettings.isWooPayGlobalThemeSupportEligible && (
						<div className="woopay-global-theme-support">
							<h4>
								{ __(
									'Checkout theme',
									'woocommerce-payments'
								) }
							</h4>
							<div className="woopay-settings__global-theme-checkbox">
								<CheckboxControl
									disabled={ ! isWooPayEnabled }
									checked={
										isWooPayGlobalThemeSupportEnabled
									}
									onChange={
										updateIsWooPayGlobalThemeSupportEnabled
									}
									label={
										<div className="woopay-settings__global-theme-label">
											{ __(
												'Enable global theme support',
												'woocommerce-payments'
											) }
											<span className="woopay-settings__badge">
												Beta
											</span>
										</div>
									}
									help={ interpolateComponents( {
										mixedString: __(
											'When enabled, WooPay checkout will be themed with your store’s brand colors and fonts. ' +
												'{{docs}}Learn more {{/docs}}',
											'woocommerce-payments'
										),
										components: {
											docs: (
												/* eslint-disable-next-line jsx-a11y/anchor-has-content */
												<a
													target="_blank"
													rel="noreferrer"
													// eslint-disable-next-line max-len
													href="https://woocommerce.com/document/woopay-merchant-documentation/#checkout-appearance"
												/>
											),
										},
									} ) }
									__nextHasNoMarginBottom
								/>
							</div>
						</div>
					) }
					<TextareaControl
						label={ __(
							'Checkout policies',
							'woocommerce-payments'
						) }
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
										// @ts-expect-error: children is provided when interpolating the component
										// eslint-disable-next-line max-len
										<ExternalLink href="https://woocommerce.com/document/woopay-merchant-documentation/#checkout-appearance" />
									),
								}
						} ) }
						value={ woopayCustomMessage }
						onChange={ setWooPayCustomMessage }
						__nextHasNoMarginBottom
					/>
					{ /* eslint-disable-next-line @wordpress/no-base-control-with-label-without-id */ }
					<BaseControl
						className="woopay-settings__preview"
						label={ __(
							'Preview of checkout',
							'woocommerce-payments'
						) }
						__nextHasNoMarginBottom
					>
						<WooPayPreview
							storeName={ wcSettings.siteTitle }
							storeLogo={ woopayStoreLogo }
							customMessage={ woopayCustomMessage }
						/>
					</BaseControl>
				</CardBody>
			) }

			{ section === 'general' && (
				<GeneralPaymentRequestButtonSettings type="woopay" />
			) }
		</Card>
	);
};

export default WooPaySettings;
