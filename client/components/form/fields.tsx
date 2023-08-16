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
	ControlProps as SelectControlProps,
	Item as SelectItem,
} from '../custom-select-control';
import PhoneNumberControl, {
	PhoneNumberControlProps,
} from '../phone-number-control';
import GroupedSelectControl, {
	GroupedSelectControlProps,
	ListItem as GroupedSelectItem,
} from '../grouped-select-control';
import './style.scss';

interface CommonProps {
	error?: string;
}

type Item = SelectItem | GroupedSelectItem;
export type TextFieldProps = TextControl.Props & CommonProps;
export type SelectFieldProps< ItemType > = SelectControlProps< ItemType > &
	CommonProps;
export type PhoneNumberFieldProps = PhoneNumberControlProps & CommonProps;
export type GroupedSelectFieldProps< ItemType > = GroupedSelectControlProps<
	ItemType
> &
	CommonProps;

type FieldProps< ItemType > = {
	component: 'text' | 'select' | 'phone' | 'grouped-select';
} & (
	| TextFieldProps
	| SelectFieldProps< ItemType >
	| PhoneNumberFieldProps
	| GroupedSelectFieldProps< ItemType >
 );

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
			props = rest as SelectFieldProps< SelectItem >;
			field = <CustomSelectControl { ...props } />;
			break;
		case 'phone':
			props = rest as PhoneNumberFieldProps;
			field = <PhoneNumberControl { ...props } />;
			break;
		case 'grouped-select':
			props = rest as GroupedSelectFieldProps< GroupedSelectItem >;
			field = <GroupedSelectControl { ...props } />;
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

export const SelectField = < ItemType extends SelectItem >(
	props: SelectFieldProps< ItemType >
): JSX.Element => <Field component={ 'select' } { ...props } />;

export const PhoneNumberField: React.FC< PhoneNumberControlProps > = (
	props
) => <Field component={ 'phone' } { ...props } />;

export const GroupedSelectField = < ItemType extends GroupedSelectItem >(
	props: GroupedSelectControlProps< ItemType >
): JSX.Element => <Field component={ 'grouped-select' } { ...props } />;

export default Field;
