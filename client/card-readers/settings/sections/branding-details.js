/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { ColorPaletteControl } from '@wordpress/block-editor';
import BrandingFileUpload from '../file-upload';

/**
 * Internal dependencies
 */
import {
	useAccountBrandingPrimaryColor,
	useAccountBrandingSecondaryColor,
	useAccountBrandingLogo,
	useAccountBrandingIcon,
} from '../../../data';

const BrandingDetailsSection = () => {
	const [
		accountBrandingPrimaryColor,
		setAccountBrandingPrimaryColor,
	] = useAccountBrandingPrimaryColor();

	const [
		accountBrandingSecondaryColor,
		setAccountBrandingSecondaryColor,
	] = useAccountBrandingSecondaryColor();

	const [
		getAccountBrandingLogo,
		setAccountBrandingLogo,
	] = useAccountBrandingLogo();

	const [
		getAccountBrandingIcon,
		setAccountBrandingIcon,
	] = useAccountBrandingIcon();

	return (
		<>
			<h4>{ __( 'Branding', 'woocommerce-payments' ) }</h4>

			<BrandingFileUpload
				fieldKey="branding-logo"
				label={ __( 'Logo', 'woocommerce-payments' ) }
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

			<BrandingFileUpload
				fieldKey="branding-icon"
				label={ __( 'Icon', 'woocommerce-payments' ) }
				accept="image/png, image/jpeg"
				disabled={ false }
				help={ __(
					'Upload a .png or .jpg file. For best results upload a 100px x 100px .png file.',
					'woocommerce-payments'
				) }
				purpose="business_icon"
				value={ getAccountBrandingIcon }
				updateFileID={ setAccountBrandingIcon }
			/>

			<ColorPaletteControl
				onChange={ setAccountBrandingPrimaryColor }
				disableCustomColors={ false }
				colors={ [] }
				value={ accountBrandingPrimaryColor }
				label={ __( 'Primary color', 'woocommerce-payments' ) }
			/>

			<ColorPaletteControl
				onChange={ setAccountBrandingSecondaryColor }
				disableCustomColors={ false }
				colors={ [] }
				value={ accountBrandingSecondaryColor }
				label={ __( 'Secondary color', 'woocommerce-payments' ) }
			/>
		</>
	);
};

export default BrandingDetailsSection;
