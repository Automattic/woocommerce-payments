/**
 * External dependencies
 */
import React, { useCallback, useEffect } from 'react';
// @ts-expect-error The component exists.
import { TextControlWithAffixes } from '@woocommerce/components';
import './style.scss';

interface AmountInputProps {
	id?: string;
	label?: string;
	prefix?: string;
	value: string;
	placeholder?: string;
	help?: string;
	onChange?: ( value: string ) => void;
}

const AmountInput: React.FC< AmountInputProps > = ( {
	id,
	prefix,
	label,
	value,
	placeholder,
	help,
	onChange = () => null,
} ) => {
	// Only allow digits, a single dot, and more digits (or an empty value).
	const validateInput = useCallback(
		( subject: string ) => /^(\d+\.?\d*)?$/m.test( subject ),
		[]
	);

	const validatedValue = validateInput( value ) ? value : '';

	const [ internalValue, setInternalValue ] = React.useState(
		validatedValue
	);

	useEffect( () => {
		if ( ! validateInput( internalValue ) ) {
			onChange( '' );
		}
	}, [ validateInput, internalValue, onChange ] );

	const handleChange = ( inputValue: string ) => {
		if ( validateInput( inputValue ) ) {
			setInternalValue( inputValue );
			onChange( inputValue );
		}
	};

	return (
		<TextControlWithAffixes
			id={ id }
			className="wcpay-components-amount-input"
			label={ label }
			prefix={ prefix }
			value={ internalValue }
			data-testid="amount-input"
			onChange={ handleChange }
			placeholder={ placeholder }
			help={ help }
		/>
	);
};

export default AmountInput;
