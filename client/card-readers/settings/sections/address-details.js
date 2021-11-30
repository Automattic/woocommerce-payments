/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl, SelectControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useAccountBusinessSupportAddress } from '../../../data';

const AddressDetailsSection = () => {
	const [
		accountBusinessSupportAddress,
		accountBusinessSupportAddressCountry,
		accountBusinessSupportAddressLine1,
		accountBusinessSupportAddressLine2,
		accountBusinessSupportAddressCity,
		accountBusinessSupportAddressPostalCode,
		setAccountBusinessSupportAddress,
	] = useAccountBusinessSupportAddress();

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

	return (
		<>
			<h4>{ __( 'Business address', 'woocommerce-payments' ) }</h4>
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
