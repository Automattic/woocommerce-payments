/**
 * External dependencies
 */
import React from 'react';
import './style.scss';

const AmountInput = ( { id, prefix, value, placeholder, help, onChange } ) => {
	const validateInput = ( subject ) => {
		if ( null === subject || undefined === subject || isNaN( subject ) ) {
			return false;
		}
		// Only allow decimals, a single dot, and more decimals (or an empty value).
		return subject.match( /^(\d+\.?\d*)?$/m );
	};

	if ( null === value || undefined === value || isNaN( value ) ) {
		value = '';
	}

	const handleChange = ( inputvalue ) => {
		if ( validateInput( inputvalue ) ) {
			onChange( inputvalue );
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
