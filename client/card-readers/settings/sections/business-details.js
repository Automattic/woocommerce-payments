/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import {
	useAccountBusinessName,
	useAccountBusinessURL,
	useGetSavingError,
} from '../../../data';

const BusinessDetailsSection = () => {
	const [
		accountBusinessName,
		setAccountBusinessName,
	] = useAccountBusinessName();

	const [
		accountBusinessURL,
		setAccountBusinessURL,
	] = useAccountBusinessURL();

	const businessSuppotURLErrorMessage = useGetSavingError()?.data?.details
		?.account_business_url?.message;

	return (
		<>
			<h4>{ __( 'Business details', 'woocommerce-payments' ) }</h4>
			<TextControl
				className="card-readers-business-name-input"
				label={ __( 'Business name', 'woocommerce-payments' ) }
				value={ accountBusinessName }
				onChange={ setAccountBusinessName }
			/>
			{ businessSuppotURLErrorMessage && (
				<Notice status="error" isDismissible={ false }>
					<span
						dangerouslySetInnerHTML={ {
							__html: businessSuppotURLErrorMessage,
						} }
					/>
				</Notice>
			) }
			<TextControl
				className="card-readers-business-url-input"
				label={ __( 'Business URL', 'woocommerce-payments' ) }
				value={ accountBusinessURL }
				onChange={ setAccountBusinessURL }
				type="url"
			/>
		</>
	);
};

export default BusinessDetailsSection;
