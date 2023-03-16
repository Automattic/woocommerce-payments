/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { isEmpty, mapValues } from 'lodash';

/**
 * Internal dependencies
 */
import { useStepperContext } from 'components/stepper';
import { Item } from 'components/custom-select-control';
import {
	TextField,
	TextFieldProps,
	SelectField,
	SelectFieldProps,
} from 'components/form/fields';
import { useOnboardingContext } from './context';
import { OnboardingFields } from './types';
import { useValidation } from './validation';
import strings from './strings';

export const OnboardingForm: React.FC = ( { children } ) => {
	const { errors, touched, setTouched } = useOnboardingContext();
	const { nextStep } = useStepperContext();

	const isValid = isEmpty( errors );

	const handleContinue = () => {
		if ( isValid ) return nextStep();
		setTouched( mapValues( touched, () => true ) );
	};

	return (
		<form
			onSubmit={ ( event ) => {
				event.preventDefault();
				handleContinue();
			} }
		>
			{ children }
			<Button isPrimary onClick={ handleContinue } type="submit">
				{ strings.continue }
			</Button>
		</form>
	);
};

interface OnboardingTextFieldProps extends Partial< TextFieldProps > {
	name: keyof OnboardingFields;
}

export const OnboardingTextField: React.FC< OnboardingTextFieldProps > = ( {
	name,
	...rest
} ) => {
	const { data, setData } = useOnboardingContext();
	const { validate, error } = useValidation( name );

	return (
		<TextField
			label={ strings.fields[ name ] }
			value={ data[ name ] || '' }
			onChange={ ( value: string ) => {
				setData( { [ name ]: value } );
				validate( value );
			} }
			error={ error() }
			{ ...rest }
		/>
	);
};

interface OnboardingSelectFieldProps< ItemType >
	extends Partial< Omit< SelectFieldProps< ItemType >, 'onChange' > > {
	name: keyof OnboardingFields;
	onChange?: ( name: keyof OnboardingFields, item?: ItemType ) => void;
}

export const OnboardingSelectField = < ItemType extends Item >( {
	name,
	onChange,
	...rest
}: OnboardingSelectFieldProps< ItemType > ): JSX.Element => {
	const { data, setData } = useOnboardingContext();
	const { validate, error } = useValidation( name );

	return (
		<SelectField
			label={ strings.fields[ name ] }
			value={ rest.options?.find(
				( item ) => item.key === data[ name ]
			) }
			placeholder={
				( strings.placeholders as Record< string, string > )[ name ]
			}
			onChange={ ( { selectedItem } ) => {
				if ( onChange ) {
					onChange?.( name, selectedItem );
				} else {
					setData( { [ name ]: selectedItem?.key } );
				}
				validate( selectedItem?.key );
			} }
			options={ [] }
			error={ error() }
			{ ...rest }
		/>
	);
};
