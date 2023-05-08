/**
 * External dependencies
 */
import React from 'react';
import { TextControl } from '@wordpress/components';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import CustomSelectControl, {
	ControlProps,
	Item,
} from 'components/custom-select-control';
import PhoneNumberControl, {
	PhoneNumberControlProps,
} from '../phone-number-control';
import './style.scss';

interface CommonProps {
	error?: string;
}

export type TextFieldProps = TextControl.Props & CommonProps;
export type SelectFieldProps< ItemType > = ControlProps< ItemType > &
	CommonProps;
export type PhoneNumberFieldProps = PhoneNumberControlProps & CommonProps;

type FieldProps< ItemType > = {
	component: 'text' | 'select' | 'phone';
} & ( TextFieldProps | SelectFieldProps< ItemType > | PhoneNumberFieldProps );

const Field = < ItemType extends Item >( {
	component,
	error,
	...rest
}: FieldProps< ItemType > ): JSX.Element => {
	if ( error ) {
		rest.className = classNames( rest.className, 'has-error' );
	}

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
		case 'phone':
			props = rest as PhoneNumberFieldProps;
			field = <PhoneNumberControl { ...props } />;
			break;
	}

	return (
		<>
			{ field }
			{ error && (
				<div className="components-form-field__error">{ error }</div>
			) }
		</>
	);
};

export const TextField: React.FC< TextFieldProps > = ( props ) => (
	<Field component={ 'text' } { ...props } />
);

export const SelectField = < ItemType extends Item >(
	props: SelectFieldProps< ItemType >
): JSX.Element => <Field component={ 'select' } { ...props } />;

export const PhoneNumberField: React.FC< PhoneNumberControlProps > = (
	props
) => <Field component={ 'phone' } { ...props } />;

export default Field;
