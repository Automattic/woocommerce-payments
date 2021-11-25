/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { ColorPaletteControl } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import {
	useAccountBrandingPrimaryColor,
	useAccountBrandingSecondaryColor,
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

	return (
		<>
			<h4>{ __( 'Branding', 'woocommerce-payments' ) }</h4>
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
