/**
 * External dependencies
 */
import React from 'react';
import './style.scss';

const AmountInput = ( { id, prefix, value, placeholder, help, onChange } ) => {
	return (
		<div className="components-base-control components-amount_input--container">
			<div className="components-base-control__field components-amount_input--input-container">
				<span className="components-amount_input--prefix">
					{ prefix }
				</span>
				<input
					id={ id }
					placeholder={ placeholder }
					value={ value }
					type="text"
					onChange={ ( e ) => onChange( e.target.value ) }
					className="components-text-control__input components-amount_input--input"
				/>
			</div>
			<span className="components-amount_input--help-text">{ help }</span>
		</div>
	);
};

export default AmountInput;
