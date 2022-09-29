/**
 * External dependencies
 */
import React, { useState } from 'react';
import {
	Button,
	Card,
	CardBody,
	SelectControl,
	TextControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import strings from './strings';
import { fromDotNotation } from './utils';

const countryOptions = [
	{
		label: strings.countries.usa,
		value: 'US',
	},
];

const businessTypeOptions = [
	{
		label: strings.businessTypes.individual,
		value: 'individual',
	},
];

const mccOptions = [
	{
		label: strings.mcc.computerSoftware,
		value: '5734',
	},
	{
		label: strings.mcc.books,
		value: '5942',
	},
];

const controls = {
	country: {
		label: strings.controls.country,
		initialValue: countryOptions[ 0 ].value,
	},
	business_type: {
		label: strings.controls.businessType,
		initialValue: businessTypeOptions[ 0 ].value,
	},
	business_name: {
		label: strings.controls.businessName,
		initialValue: wcSettings.siteTitle,
	},
	url: {
		label: strings.controls.url,
		initialValue: wcSettings.homeUrl.replace( /.+\/\/(.+)\/.*/, '$1' ),
	},
	mcc: {
		label: strings.controls.mcc,
		initialValue: mccOptions[ 0 ].value,
	},
	email: {
		label: strings.controls.email,
		initialValue: wcpaySettings.currentUserEmail,
	},
	'individual.first_name': {
		label: strings.controls.firstName,
	},
	'individual.last_name': {
		label: strings.controls.lastName,
	},
};

const initialValues = Object.keys( controls ).reduce(
	( values, key ) => ( {
		...values,
		[ key ]: controls[ key ].initialValue ?? '',
	} ),
	{}
);

const OnboardingForm = () => {
	const [ accountData, setAccountData ] = useState( initialValues );

	const getControlProps = ( name ) => ( {
		label: controls[ name ].label,
		value: accountData[ name ],
		onChange: ( value ) =>
			setAccountData( { ...accountData, [ name ]: value } ),
	} );

	const handleSubmit = () => {
		alert( JSON.stringify( fromDotNotation( accountData ), null, 2 ) );
	};

	return (
		<Card size="large">
			<CardBody>
				<h2>{ strings.progressiveOnboarding }</h2>
				<SelectControl
					{ ...getControlProps( 'country' ) }
					options={ countryOptions }
				/>
				<SelectControl
					{ ...getControlProps( 'business_type' ) }
					options={ businessTypeOptions }
				/>
				<TextControl { ...getControlProps( 'business_name' ) } />
				<TextControl { ...getControlProps( 'url' ) } />
				<SelectControl
					{ ...getControlProps( 'mcc' ) }
					options={ mccOptions }
				/>
				<TextControl { ...getControlProps( 'email' ) } />
				<TextControl
					{ ...getControlProps( 'individual.first_name' ) }
				/>
				<TextControl { ...getControlProps( 'individual.last_name' ) } />
				<Button isPrimary onClick={ handleSubmit }>
					{ strings.submit }
				</Button>
			</CardBody>
		</Card>
	);
};

export default OnboardingForm;
