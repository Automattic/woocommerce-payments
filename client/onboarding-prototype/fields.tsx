/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import strings from './strings';
import { useOnboardingContext } from './context';
import { OnboardingFields } from './types';
import {
	TextField,
	TextFieldProps,
	SelectField,
	SelectFieldProps,
} from 'components/form/fields';
import { Item } from 'components/custom-select-control';

interface OnboardingTextFieldProps extends Partial< TextFieldProps > {
	name: keyof OnboardingFields;
}

export const OnboardingTextField: React.FC< OnboardingTextFieldProps > = ( {
	name,
	...rest
} ) => {
	const { data, setData } = useOnboardingContext();

	return (
		<TextField
			label={ strings.fields[ name ] }
			value={ data[ name ] || '' }
			onChange={ ( value: string ) => setData( { [ name ]: value } ) }
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

	return (
		<SelectField
			label={ strings.fields[ name ] }
			value={ rest.options?.find(
				( item ) => item.key === data[ name ]
			) }
			placeholder={
				( strings.placeholders as Record< string, string > )[ name ]
			}
			onChange={ ( { selectedItem } ) =>
				onChange?.( name, selectedItem ) ||
				setData( { [ name ]: selectedItem?.key } )
			}
			options={ [] }
			{ ...rest }
		/>
	);
};
