/**
 * External dependencies
 */
import React from 'react';
import { TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CustomSelectControl from 'components/custom-select-control';

type TextFieldProps = Omit<
	React.ComponentProps< typeof TextControl >,
	'type'
>;
type SelectFieldProps = React.ComponentProps< typeof CustomSelectControl >;

type FieldProps = {
	type: 'text' | 'select';
} & ( TextFieldProps | SelectFieldProps );

const Field: React.FC< FieldProps > = ( { type, ...rest } ) => {
	let props, field;
	switch ( type ) {
		case 'text':
			props = rest as TextFieldProps;
			field = <TextControl { ...props } />;
			break;
		case 'select':
			props = rest as SelectFieldProps;
			field = <CustomSelectControl { ...props } />;
			break;
	}

	return <>{ field }</>;
};

export const TextField: React.FC< TextFieldProps > = ( props ) => (
	<Field type={ 'text' } { ...props } />
);

export const SelectField: React.FC< SelectFieldProps > = ( props ) => (
	<Field type={ 'select' } { ...props } />
);

export default Field;
