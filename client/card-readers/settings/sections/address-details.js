/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl, SelectControl, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import {
	useAccountBusinessSupportAddress,
	useGetSavingError,
} from '../../../data';

const AddressDetailsSection = () => {
	const [
		accountBusinessSupportAddress,
		accountBusinessSupportAddressCountry,
		accountBusinessSupportAddressLine1,
		accountBusinessSupportAddressLine2,
		accountBusinessSupportAddressCity,
		accountBusinessSupportAddressState,
		accountBusinessSupportAddressPostalCode,
		setAccountBusinessSupportAddress,
	] = useAccountBusinessSupportAddress();

	const businessSuppotAddressErrorMessage = useGetSavingError()?.data?.details
		?.account_business_support_address?.message;

	const handleAddressPropertyChange = ( property, value ) => {
		setAccountBusinessSupportAddress( {
			...accountBusinessSupportAddress,
			[ property ]: value,
		} );
	};

	const unescapeHtmlEntities = ( string ) => {
		const doc = new DOMParser().parseFromString( string, 'text/html' );
		return doc.documentElement.textContent;
	};

	const countriesOptions = Object.entries( wcSettings.countries ).map(
		( [ value, label ] ) => ( {
			label: unescapeHtmlEntities( label ),
			value: value,
		} )
	);

	const countryStates =
		wcpaySettings.connect.availableStates[
			accountBusinessSupportAddressCountry
		] || [];
	const countryStatesOptions = Object.entries( countryStates ).map(
		( [ value, label ] ) => ( {
			label: unescapeHtmlEntities( label ),
			value: unescapeHtmlEntities( label ),
			country: value,
		} )
	);

	return (
		<>
			<h4>{ __( 'Business address', 'woocommerce-payments' ) }</h4>
			{ businessSuppotAddressErrorMessage && (
				<Notice status="error" isDismissible={ false }>
					<span>{ businessSuppotAddressErrorMessage }</span>
				</Notice>
			) }
			<SelectControl
				label={ __( 'Country', 'woocommerce-payments' ) }
				value={ accountBusinessSupportAddressCountry }
				onChange={ ( value ) =>
					handleAddressPropertyChange( 'country', value )
				}
				options={ countriesOptions }
			/>
			<TextControl
				className="card-readers-support-address-line1-input"
				label={ __( 'Address line 1', 'woocommerce-payments' ) }
				value={ accountBusinessSupportAddressLine1 }
				onChange={ ( value ) =>
					handleAddressPropertyChange( 'line1', value )
				}
			/>
			<TextControl
				className="card-readers-support-address-line2-input"
				label={ __( 'Address line 2', 'woocommerce-payments' ) }
				value={ accountBusinessSupportAddressLine2 }
				onChange={ ( value ) =>
					handleAddressPropertyChange( 'line2', value )
				}
			/>
			<TextControl
				className="card-readers-support-address-city"
				label={ __( 'City', 'woocommerce-payments' ) }
				value={ accountBusinessSupportAddressCity }
				onChange={ ( value ) =>
					handleAddressPropertyChange( 'city', value )
				}
			/>
			{ 0 < countryStatesOptions.length && (
				<SelectControl
					label={ __( 'State', 'woocommerce-payments' ) }
					value={ accountBusinessSupportAddressState }
					onChange={ ( value ) =>
						handleAddressPropertyChange( 'state', value )
					}
					options={ countryStatesOptions }
				/>
			) }
			<TextControl
				className="card-readers-support-address-postcode"
				label={ __( 'Postal code', 'woocommerce-payments' ) }
				value={ accountBusinessSupportAddressPostalCode }
				onChange={ ( value ) =>
					handleAddressPropertyChange( 'postal_code', value )
				}
			/>
		</>
	);
};

export default AddressDetailsSection;
