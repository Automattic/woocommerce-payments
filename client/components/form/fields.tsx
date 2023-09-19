/**
 * External dependencies
 */
import React, { forwardRef } from 'react';
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

export type TextFieldProps = TextControl.Props & CommonProps;
export type SelectFieldProps< ItemType > = SelectControlProps< ItemType > &
	CommonProps;
export type PhoneNumberFieldProps = PhoneNumberControlProps & CommonProps;
export type GroupedSelectFieldProps< ItemType > = GroupedSelectControlProps<
	ItemType
> &
	CommonProps;

/**
 * Creates a field component decorating a control to display validation errors.
 *
 * @param Control Control component to render.
 * @param props Control props plus common field props – {error?: string}.
 * @param ref Optional React reference.
 * @return Form field.
 */
const makeField = (
	Control: React.ElementType,
	props: CommonProps & Record< any, any >,
	ref?: React.Ref< any >
) => {
	const { error, ...rest } = props;
	if ( ! error ) return <Control { ...rest } ref={ ref } />;
	return (
		<>
			<Control
				{ ...rest }
				ref={ ref }
				className={ classNames( rest.className, 'has-error' ) }
			/>
			{ <div className="components-form-field__error">{ error }</div> }
		</>
	);
};

export const TextField = forwardRef< HTMLInputElement, TextFieldProps >(
	( props, ref ) => {
		return makeField( TextControl, props, ref );
	}
);

export const SelectField = < ItemType extends SelectItem >(
	props: SelectFieldProps< ItemType >
): JSX.Element => makeField( CustomSelectControl, props );

export const PhoneNumberField: React.FC< PhoneNumberFieldProps > = ( props ) =>
	makeField( PhoneNumberControl, props );

export const GroupedSelectField = < ItemType extends GroupedSelectItem >(
	props: GroupedSelectControlProps< ItemType >
): JSX.Element => makeField( GroupedSelectControl, props );
