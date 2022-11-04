/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CheckboxControl,
	RadioControl,
	TextControl,
} from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

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
	usePlatformCheckoutButtonType,
	usePlatformCheckoutButtonSize,
	usePlatformCheckoutButtonTheme,
	usePlatformCheckoutLocations,
} from 'wcpay/data';
import PlatformCheckoutButtonPreview from './platform-checkout-button-preview';

const CUSTOM_MESSAGE_MAX_LENGTH = 100;

const makeButtonSizeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			helpText: (
				<span className="payment-method-settings__option-muted-text" />
			),
		},
	} );
const buttonSizeOptions = [
	{
		label: makeButtonSizeText(
			__(
				'Default {{helpText}}(40 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'default',
	},
	{
		label: makeButtonSizeText(
			__(
				'Medium {{helpText}}(48 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'medium',
	},
	{
		label: makeButtonSizeText(
			__(
				'Large {{helpText}}(56 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'large',
	},
];
const buttonActionOptions = [
	{
		label: __( 'Only icon', 'woocommerce-payments' ),
		value: 'default',
	},
	{
		label: __( 'Buy', 'woocommerce-payments' ),
		value: 'buy',
	},
	{
		label: __( 'Donate', 'woocommerce-payments' ),
		value: 'donate',
	},
	{
		label: __( 'Book', 'woocommerce-payments' ),
		value: 'book',
	},
];

const makeButtonThemeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			br: <br />,
			helpText: (
				<span className="payment-method-settings__option-help-text" />
			),
		},
	} );
const buttonThemeOptions = [
	{
		label: makeButtonThemeText(
			__(
				'Dark {{br/}}{{helpText}}Recommended for white or light-colored backgrounds with high contrast.{{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'dark',
	},
	{
		label: makeButtonThemeText(
			__(
				'Light {{br/}}{{helpText}}Recommended for dark or colored backgrounds with high contrast.{{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'light',
	},
	{
		label: makeButtonThemeText(
			__(
				'Outline {{br/}}{{helpText}}Recommended for white or light-colored backgrounds with insufficient contrast.{{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'light-outline',
	},
];

const PlatformCheckoutSettings = ( { section } ) => {
	const [ buttonType, setButtonType ] = usePlatformCheckoutButtonType();
	const [ size, setSize ] = usePlatformCheckoutButtonSize();
	const [ theme, setTheme ] = usePlatformCheckoutButtonTheme();
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
		<div className="platform-checkout-settings">
			{ 'enable' === section && (
				<Card>
					<CardBody>
						<CheckboxControl
							checked={ isPlatformCheckoutEnabled }
							onChange={ updateIsPlatformCheckoutEnabled }
							label={ __(
								'Enable WooPay',
								'woocommerce-payments'
							) }
							help={ __(
								'When enabled, customers will be able to checkout using WooPay',
								'woocommerce-payments'
							) }
						/>
					</CardBody>
				</Card>
			) }

			{ 'appearance' === section && (
				<Card style={ { marginTop: 12 } }>
					<div className="platform-checkout-settings__preview">
						<PlatformCheckoutPreview
							storeName={ wcSettings.siteTitle }
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
								{ __(
									'Custom message',
									'woocommerce-payments'
								) }
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
								className="input-help-text"
								aria-hidden="true"
							>
								{ `${ platformCheckoutCustomMessage.length } / ${ CUSTOM_MESSAGE_MAX_LENGTH }` }
							</span>
						</div>
					</CardBody>
				</Card>
			) }

			{ 'general' === section && (
				<Card style={ { marginTop: 12 } }>
					<CardBody>
						<h4>
							{ __(
								'Show express checkouts on',
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
										'Checkout',
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
										'Product page',
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
											'cart'
										)
									}
									onChange={ makeLocationChangeHandler(
										'cart'
									) }
									label={ __(
										'Cart',
										'woocommerce-payments'
									) }
								/>
							</li>
						</ul>
						<h4>
							{ __( 'Call to action', 'woocommerce-payments' ) }
						</h4>
						<RadioControl
							className="payment-method-settings__cta-selection"
							hideLabelFromVision
							help={ __(
								'Select a button label that fits best with the flow of purchase or payment experience on your store.',
								'woocommerce-payments'
							) }
							selected={ buttonType }
							options={ buttonActionOptions }
							onChange={ setButtonType }
						/>
						<h4>{ __( 'Appearance', 'woocommerce-payments' ) }</h4>
						<RadioControl
							help={ __(
								'Note that larger buttons are more suitable for mobile use.',
								'woocommerce-payments'
							) }
							label={ __( 'Size', 'woocommerce-payments' ) }
							selected={ size }
							options={ buttonSizeOptions }
							onChange={ setSize }
						/>
						<RadioControl
							label={ __( 'Theme', 'woocommerce-payments' ) }
							selected={ theme }
							options={ buttonThemeOptions }
							onChange={ setTheme }
						/>
						<p>{ __( 'Preview', 'woocommerce-payments' ) }</p>
						<PlatformCheckoutButtonPreview />
					</CardBody>
				</Card>
			) }
		</div>
	);
};

export default PlatformCheckoutSettings;
