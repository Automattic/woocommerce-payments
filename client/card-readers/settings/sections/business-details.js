/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useAccountBusinessName, useAccountBusinessURL } from '../../../data';

const BusinessDetailsSection = () => {
	const [
		accountBusinessName,
		setAccountBusinessName,
	] = useAccountBusinessName();

	const [
		accountBusinessURL,
		setAccountBusinessURL,
	] = useAccountBusinessURL();

	return (
		<>
			<h4>{ __( 'Business details', 'woocommerce-payments' ) }</h4>
			<TextControl
				className="card-readers-business-name-input"
				label={ __( 'Business name', 'woocommerce-payments' ) }
				value={ accountBusinessName }
				onChange={ setAccountBusinessName }
			/>
			<TextControl
				className="card-readers-business-url-input"
				label={ __( 'Business URL', 'woocommerce-payments' ) }
				value={ accountBusinessURL }
				onChange={ setAccountBusinessURL }
			/>
		</>
	);
};

export default BusinessDetailsSection;
