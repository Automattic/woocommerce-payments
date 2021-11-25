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

const ReadersSettingsFields = () => {
	const [
		accountBusinessSupportAddress,
		accountBusinessSupportAddressCountry,
		accountBusinessSupportAddressLine1,
		accountBusinessSupportAddressLine2,
		accountBusinessSupportAddressCity,
		accountBusinessSupportAddressPostalCode,
		setAccountBusinessSupportAddress,
	] = useAccountBusinessSupportAddress();

	const handleAddresPropertyChange = ( property, value ) => {
		setAccountBusinessSupportAddress( {
			...accountBusinessSupportAddress,
			[ property ]: value,
		} );
	};

	const countriesOptions = Object.entries(
		wcSettings.countries
	).map( ( [ value, label ] ) => ( { label, value: value } ) );

	return (
		<>
			<h4>{ __( 'Business address', 'woocommerce-payments' ) }</h4>
			<SelectControl
				label={ __( 'Country', 'woocommerce-payments' ) }
				value={ accountBusinessSupportAddressCountry }
				onChange={ ( value ) =>
					handleAddresPropertyChange( 'country', value )
				}
				options={ countriesOptions }
			/>
			<TextControl
				className="card-readers-support-address-line1-input"
				label={ __( 'Address line 1', 'woocommerce-payments' ) }
				value={ accountBusinessSupportAddressLine1 }
				onChange={ ( value ) =>
					handleAddresPropertyChange( 'line1', value )
				}
			/>
			<TextControl
				className="card-readers-support-address-line2-input"
				label={ __( 'Address line 2', 'woocommerce-payments' ) }
				value={ accountBusinessSupportAddressLine2 }
				onChange={ ( value ) =>
					handleAddresPropertyChange( 'line2', value )
				}
			/>
			<TextControl
				className="card-readers-support-address-city"
				label={ __( 'City', 'woocommerce-payments' ) }
				value={ accountBusinessSupportAddressCity }
				onChange={ ( value ) =>
					handleAddresPropertyChange( 'city', value )
				}
			/>
			<TextControl
				className="card-readers-support-address-postcode"
				label={ __( 'Postal code', 'woocommerce-payments' ) }
				value={ accountBusinessSupportAddressPostalCode }
				onChange={ ( value ) =>
					handleAddresPropertyChange( 'postal_code', value )
				}
			/>
		</>
	);
};

export default ReadersSettingsFields;
