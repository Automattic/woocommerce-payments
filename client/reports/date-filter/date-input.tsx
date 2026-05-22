/** @format */

/**
 * External dependencies
 */
import React, { useId } from 'react';

const YMD = /^\d{4}-\d{2}-\d{2}$/;

export interface DateInputProps {
	value: string;
	onChange: ( next: string ) => void;
	label: string;
	id?: string;
	min?: string;
	max?: string;
}

/**
 * Native date input that emits YYYY-MM-DD strings. Browsers render a localized
 * dd/mm/yyyy presentation; the wire value stays canonical so it round-trips
 * cleanly through URL and REST.
 */
export const DateInput: React.FC< DateInputProps > = ( {
	value,
	onChange,
	label,
	id,
	min,
	max,
} ) => {
	const autoId = useId();
	const inputId = id ?? autoId;
	return (
		<div className="wcpay-date-filter__date-field">
			<label
				className="wcpay-date-filter__date-label"
				htmlFor={ inputId }
			>
				{ label }
			</label>
			<input
				id={ inputId }
				type="date"
				className="wcpay-date-filter__date-input"
				value={ YMD.test( value ) ? value : '' }
				min={ min }
				max={ max }
				onChange={ ( event ) => {
					const next = event.target.value;
					// Allow blanks to flow through; consumers decide whether to
					// commit. Reject obviously bad shapes.
					if ( next === '' || YMD.test( next ) ) {
						onChange( next );
					}
				} }
			/>
		</div>
	);
};
