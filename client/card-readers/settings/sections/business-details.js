/** @format */
/**
 * External dependencies
 */
import { React, useLayoutEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useAccountBusinessName, useAccountBusinessURL } from '../../../data';

const BusinessDetailsSection = ( { isDisabled, setDisabled } ) => {
	const [
		accountBusinessName,
		setAccountBusinessName,
	] = useAccountBusinessName();

	const [
		accountBusinessURL,
		setAccountBusinessURL,
	] = useAccountBusinessURL();

	useLayoutEffect( () => {
		const businessUrl = document.querySelector(
			'.card-readers-business-url-input input'
		);
		businessUrl.focus();
		businessUrl.blur();
	}, [] );

	const validateBusinessURL = ( event ) => {
		if ( event.target.checkValidity() ) {
			setDisabled( false );
		} else {
			setDisabled( true );
		}
	};

	return (
		<>
			<h4>{ __( 'Business details', 'woocommerce-payments' ) }</h4>
			<TextControl
				className="card-readers-business-name-input"
				label={ __( 'Business name', 'woocommerce-payments' ) }
				value={ accountBusinessName }
				onChange={ setAccountBusinessName }
			/>
			{ isDisabled && (
				<Notice status="error" isDismissible={ false }>
					<span>
						{ __(
							'Error: Invalid business URL, should start with http:// or https:// prefix.',
							'woocommerce-payments'
						) }
					</span>
				</Notice>
			) }
			<TextControl
				className="card-readers-business-url-input"
				label={ __( 'Business URL', 'woocommerce-payments' ) }
				value={ accountBusinessURL }
				onChange={ setAccountBusinessURL }
				onBlur={ validateBusinessURL }
				type="url"
			/>
		</>
	);
};

export default BusinessDetailsSection;
