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
	// Only allow decimals, a single dot, and more decimals (or an empty value).
	const validateInput = useCallback(
		( subject ) => /^(\d+\.?\d*)?$/m.test( subject ),
		[]
	);

	useEffect( () => {
		if ( ! validateInput( value ) ) {
			onChange( '' );
		}
	}, [ validateInput, value, onChange ] );

	if ( isNaN( Number( value ) ) || null === value ) value = '';

	const handleChange = ( inputValue: string ) => {
		if ( validateInput( inputValue ) ) {
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
					value={ value }
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
