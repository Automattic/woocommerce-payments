/**
 * External dependencies
 */
import React from 'react';
import { TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CustomSelectControl, {
	ControlProps,
	Item,
} from 'components/custom-select-control';

export type TextFieldProps = TextControl.Props;
export type SelectFieldProps< ItemType > = ControlProps< ItemType >;

type FieldProps< ItemType > = {
	component: 'text' | 'select';
} & ( TextFieldProps | SelectFieldProps< ItemType > );

const Field = < ItemType extends Item >( {
	component,
	...rest
}: FieldProps< ItemType > ): JSX.Element => {
	let props, field;
	switch ( component ) {
		case 'text':
			props = rest as TextFieldProps;
			field = <TextControl { ...props } />;
			break;
		case 'select':
			props = rest as SelectFieldProps< ItemType >;
			field = <CustomSelectControl { ...props } />;
			break;
	}

	return <>{ field }</>;
};

export const TextField: React.FC< TextFieldProps > = ( props ) => (
	<Field component={ 'text' } { ...props } />
);

export const SelectField = < ItemType extends Item >(
	props: SelectFieldProps< ItemType >
): JSX.Element => <Field component={ 'select' } { ...props } />;

export default Field;
