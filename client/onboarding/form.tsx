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
import { Item as SelectItem } from 'components/custom-select-control';
import { ListItem as GroupedSelectItem } from 'components/grouped-select-control';
import {
	GroupedSelectField,
	GroupedSelectFieldProps,
	PhoneNumberField,
	PhoneNumberFieldProps,
	SelectField,
	SelectFieldProps,
	TextField,
	TextFieldProps,
} from 'components/form/fields';
import { useOnboardingContext } from './context';
import { OnboardingFields } from './types';
import { useValidation } from './validation';
import { trackStepCompleted } from './tracking';
import strings from './strings';

export const OnboardingForm: React.FC = ( { children } ) => {
	const { errors, touched, setTouched } = useOnboardingContext();
	const { currentStep, nextStep } = useStepperContext();

	const handleContinue = () => {
		if ( isEmpty( errors ) ) {
			trackStepCompleted( currentStep );
			return nextStep();
		}
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
			<Button isPrimary type="submit" className="stepper__cta">
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
	const { data, setData, touched } = useOnboardingContext();
	const { validate, error } = useValidation( name );

	return (
		<TextField
			label={ strings.fields[ name ] }
			value={ data[ name ] || '' }
			onChange={ ( value: string ) => {
				setData( { [ name ]: value } );
				if ( touched[ name ] ) validate( value );
			} }
			onBlur={ () => validate() }
			onKeyDown={ ( event: React.KeyboardEvent< HTMLInputElement > ) => {
				if ( event.key === 'Enter' ) validate();
			} }
			error={ error() }
			{ ...rest }
		/>
	);
};

interface OnboardingPhoneNumberFieldProps
	extends Partial< PhoneNumberFieldProps > {
	name: keyof OnboardingFields;
}

export const OnboardingPhoneNumberField: React.FC< OnboardingPhoneNumberFieldProps > = ( {
	name,
	...rest
} ) => {
	const { data, setData, temp, setTemp, touched } = useOnboardingContext();
	const { validate, error } = useValidation( name );

	return (
		<PhoneNumberField
			label={ strings.fields[ name ] }
			value={ data[ name ] || '' }
			country={ temp.phoneCountryCode || wcpaySettings.connect.country }
			onChange={ ( value: string, phoneCountryCode: string ) => {
				setTemp( { phoneCountryCode } );
				setData( { [ name ]: value } );
				if ( touched[ name ] ) validate( value );
			} }
			onBlur={ () => validate() }
			error={ error() }
			onKeyDown={ ( event: React.KeyboardEvent< HTMLInputElement > ) => {
				if ( event.key === 'Enter' ) validate();
			} }
			{ ...rest }
		/>
	);
};

interface OnboardingSelectFieldProps< ItemType >
	extends Partial< Omit< SelectFieldProps< ItemType >, 'onChange' > > {
	name: keyof OnboardingFields;
	onChange?: ( name: keyof OnboardingFields, item?: ItemType | null ) => void;
}

export const OnboardingSelectField = < ItemType extends SelectItem >( {
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

interface OnboardingGroupedSelectFieldProps< ItemType >
	extends Partial< Omit< GroupedSelectFieldProps< ItemType >, 'onChange' > > {
	name: keyof OnboardingFields;
	onChange?: ( name: keyof OnboardingFields, item?: ItemType | null ) => void;
}

export const OnboardingGroupedSelectField = <
	ListItemType extends GroupedSelectItem
>( {
	name,
	onChange,
	...rest
}: OnboardingGroupedSelectFieldProps< ListItemType > ): JSX.Element => {
	const { data, setData } = useOnboardingContext();
	const { validate, error } = useValidation( name );

	return (
		<GroupedSelectField
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
