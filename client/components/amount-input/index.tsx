/**
 * External dependencies
 */
import React, { useCallback, useEffect } from 'react';
import './style.scss';

interface AmountInputProps {
	id?: string;
	prefix?: string;
	value: string;
	placeholder?: string;
	help?: string;
	onChange?: ( value: string ) => void;
}

const AmountInput: React.FC< AmountInputProps > = ( {
	id,
	prefix,
	value,
	placeholder,
	help,
	onChange = () => null,
} ) => {
	// Only allow digits, a single dot, and more digits (or an empty value).
	const validateInput = useCallback(
		( subject ) => /^(\d+\.?\d*)?$/m.test( subject ),
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

	if ( isNaN( Number( value ) ) || null === value || '0' === value )
		value = '';

	const handleChange = ( inputValue: string ) => {
		if ( validateInput( inputValue ) ) {
			setInternalValue( inputValue );
			onChange( inputValue );
		}
	};

	return (
		<div className="components-base-control components-amount-input__container">
			<div className="components-base-control__field components-amount-input__input_container">
				{ prefix && (
					<span className="components-amount-input__prefix">
						{ prefix }
					</span>
				) }
				<input
					id={ id }
					placeholder={ placeholder }
					value={ internalValue }
					data-testid="amount-input"
					onChange={ ( e ) => handleChange( e.target.value ) }
					className="components-text-control__input components-amount-input__input"
				/>
			</div>
			<span className="components-amount-input__help_text">{ help }</span>
		</div>
	);
};

export default AmountInput;
