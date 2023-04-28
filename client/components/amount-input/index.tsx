/**
 * External dependencies
 */
import React from 'react';
import './style.scss';

interface AmountInputProps {
	id?: string;
	prefix?: string;
	value: string;
	placeholder?: string;
	help?: string;
	onChange?: ( value: string ) => void;
}

// Only allow digits, a single dot, and more digits (or an empty value).
const validateInput = ( subject: string ) => /^(\d+\.?\d*)?$/m.test( subject );

const AmountInput: React.FC< AmountInputProps > = ( {
	id,
	prefix,
	value,
	placeholder,
	help,
	onChange = () => null,
} ) => {
	if ( isNaN( Number( value ) ) ) value = '';

	const handleChange = ( inputValue: string ) => {
		if ( validateInput( inputValue ) ) {
			onChange( inputValue );
		} else {
			onChange( '' );
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
