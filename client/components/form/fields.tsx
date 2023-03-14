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

export type TextFieldProps = Omit< TextControl.Props, 'type' >;
export type SelectFieldProps< ItemType > = ControlProps< ItemType >;

type FieldProps< ItemType > = {
	type: 'text' | 'select';
} & ( TextFieldProps | SelectFieldProps< ItemType > );

const Field = < ItemType extends Item >( {
	type,
	...rest
}: FieldProps< ItemType > ): JSX.Element => {
	let props, field;
	switch ( type ) {
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
	<Field type={ 'text' } { ...props } />
);

export const SelectField = < ItemType extends Item >(
	props: SelectFieldProps< ItemType >
): JSX.Element => <Field type={ 'select' } { ...props } />;

export default Field;
