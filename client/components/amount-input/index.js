/**
 * External dependencies
 */
import React from 'react';
import './style.scss';

const AmountInput = ( { id, prefix, value, placeholder, help, onChange } ) => {
	const handleChange = ( e ) => {
		// Only allow decimals, a single dot, and more decimals (or an empty value).
		if ( e.target.value.match( /^(\d+\.?\d*)?$/m ) ) {
			onChange( e.target.value );
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
					value={ isNaN( value ) ? '' : value }
					type="text"
					data-testid="amount-input"
					onChange={ ( e ) => handleChange( e ) }
					className="components-text-control__input components-amount-input__input"
				/>
			</div>
			<span className="components-amount-input__help_text">{ help }</span>
		</div>
	);
};

export default AmountInput;
