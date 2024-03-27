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
			<Button
				variant={ 'primary' }
				type="submit"
				className="stepper__cta"
			>
				{ strings.continue }
			</Button>
		</form>
	);
};

interface OnboardingTextFieldProps extends Partial< TextFieldProps > {
	name: keyof OnboardingFields;
}

export const OnboardingTextField: React.FC< OnboardingTextFieldProps > = (
	props
) => {
	const { name } = props;
	const { data, setData, touched } = useOnboardingContext();
	const { validate, error } = useValidation( name );
	const inputRef = React.useRef< HTMLInputElement >( null );

	return (
		<TextField
			ref={ inputRef as any }
			label={ strings.fields[ name ] }
			value={ data[ name ] || '' }
			onChange={ ( value: string ) => {
				setData( { [ name ]: value } );
				if (
					touched[ name ] ||
					inputRef.current !==
						inputRef.current?.ownerDocument.activeElement
				)
					validate( value );
			} }
			onBlur={ () => validate() }
			onKeyDown={ ( event: React.KeyboardEvent< HTMLInputElement > ) => {
				if ( event.key === 'Enter' ) validate();
			} }
			error={ error() }
			{ ...props }
		/>
	);
};

interface OnboardingSelectFieldProps< ItemType >
	extends Partial< Omit< SelectFieldProps< ItemType >, 'onChange' > > {
	name: keyof OnboardingFields;
	onChange?: ( name: keyof OnboardingFields, item?: ItemType | null ) => void;
}

export const OnboardingSelectField = < ItemType extends SelectItem >( {
	onChange,
	...rest
}: OnboardingSelectFieldProps< ItemType > ): JSX.Element => {
	const { name } = rest;
	const { data, setData } = useOnboardingContext();
	const { validate, error } = useValidation( name );

	return (
		<SelectField
			label={ strings.fields[ name ] }
			value={ rest.options?.find(
				( item ) => item.key === data[ name ]
			) }
			placeholder={
				( strings.placeholders as Record< string, string > )[ name ] ??
				strings.placeholders.generic
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
	onChange,
	...rest
}: OnboardingGroupedSelectFieldProps< ListItemType > ): JSX.Element => {
	const { name } = rest;
	const { data, setData } = useOnboardingContext();
	const { validate, error } = useValidation( name );

	return (
		<GroupedSelectField
			label={ strings.fields[ name ] }
			value={ rest.options?.find(
				( item ) => item.key === data[ name ]
			) }
			placeholder={
				( strings.placeholders as Record< string, string > )[ name ] ??
				strings.placeholders.generic
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
