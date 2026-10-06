/**
 * External dependencies
 */
import React from 'react';
import {
	BaseControl,
	RangeControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import WooPayFileUpload from '../settings/express-checkout-settings/file-upload';
import WooPayPreview from '../settings/express-checkout-settings/woopay-preview';

export type Settings = Record< string, unknown >;

/** The props DataForm passes to a field's custom control. */
export type EditProps = {
	data: Settings;
	onChange: ( edits: Settings ) => void;
};

// Set for the existing WooPay settings, but not typed in the globals.
const woopayConfig = wcpaySettings as typeof wcpaySettings & {
	woopayAppearance?: unknown;
	woopayFontRules?: unknown;
	isWooPayGlobalThemeSupportEligible?: boolean;
};

export const isWooPayGlobalThemeSupportEligible = () =>
	woopayConfig.isWooPayGlobalThemeSupportEligible === true;

const maxRadius = 30;

/** The corner radius of the express checkout buttons, as a slider with a number input. */
export const BorderRadiusControl = ( { data, onChange }: EditProps ) => {
	const radius = Number( data.payment_request_button_border_radius ?? 0 );
	const setRadius = ( value: unknown ) => {
		const number = parseInt( String( value ), 10 );
		onChange( {
			payment_request_button_border_radius: Number.isNaN( number )
				? 0
				: Math.min( Math.max( number, 0 ), maxRadius ),
		} );
	};
	return (
		<BaseControl
			id="wcpay-settings-screen-border-radius"
			label={ __( 'Corner radius', 'woocommerce-payments' ) }
			help={ __(
				'Controls the corner roundness of express payment buttons.',
				'woocommerce-payments'
			) }
			__nextHasNoMarginBottom
		>
			<div className="wcpay-settings-screen__border-radius">
				<RangeControl
					label={ __(
						/* translators: Label for an input slider, hidden from view. Intended for accessibility. */
						'Border radius, slider',
						'woocommerce-payments'
					) }
					hideLabelFromVision
					value={ radius }
					min={ 0 }
					max={ maxRadius }
					withInputField={ false }
					onChange={ setRadius }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<NumberControl
					id="wcpay-settings-screen-border-radius"
					label={ __(
						/* translators: Label for a number input, hidden from view. Intended for accessibility. */
						'Border radius, number input',
						'woocommerce-payments'
					) }
					hideLabelFromVision
					value={ radius }
					min={ 0 }
					max={ maxRadius }
					spinControls="none"
					suffix={
						<span className="wcpay-settings-screen__border-radius-suffix">
							px
						</span>
					}
					onChange={ setRadius }
					__next40pxDefaultSize
				/>
			</div>
		</BaseControl>
	);
};

/** The WooPay checkout logo upload. */
export const StoreLogoControl = ( { data, onChange }: EditProps ) => (
	<WooPayFileUpload
		fieldKey="woopay-store-logo"
		label={ __( 'Checkout logo', 'woocommerce-payments' ) }
		accept="image/png, image/jpeg"
		help={ __(
			'Upload a custom logo. Upload a horizontal image with a white' +
				' or transparent background for best results. Use a PNG or JPG' +
				' image format. Recommended width: 512 pixels minimum.',
			'woocommerce-payments'
		) }
		purpose="business_logo"
		fileID={ String( data.woopay_store_logo ?? '' ) }
		updateFileID={ ( id ) => onChange( { woopay_store_logo: id } ) }
	/>
);

/** A preview of WooPay checkout with the logo, policies and theme being edited. */
export const WooPayCheckoutPreview = ( { item }: { item: Settings } ) => {
	const isThemed = item.is_woopay_global_theme_support_enabled === true;
	return (
		// eslint-disable-next-line @wordpress/no-base-control-with-label-without-id -- The preview isn't a form control.
		<BaseControl
			className="woopay-settings__preview"
			label={ __( 'Preview of checkout', 'woocommerce-payments' ) }
			__nextHasNoMarginBottom
		>
			<WooPayPreview
				storeName={ wcSettings.siteTitle }
				storeLogo={ item.woopay_store_logo }
				customMessage={ item.woopay_custom_message }
				appearance={ isThemed ? woopayConfig.woopayAppearance : null }
				fontRules={ isThemed ? woopayConfig.woopayFontRules : null }
			/>
		</BaseControl>
	);
};
