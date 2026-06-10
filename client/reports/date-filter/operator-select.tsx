/** @format */

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import { operatorLabel } from './formatters';
import type { DateOperator } from './types';

const operators: DateOperator[] = [ 'on', 'before', 'after', 'between' ];

export interface OperatorSelectProps {
	value: DateOperator;
	onChange: ( next: DateOperator ) => void;
	id?: string;
}

export const OperatorSelect: React.FC< OperatorSelectProps > = ( {
	value,
	onChange,
	id,
} ) => (
	<select
		id={ id }
		className="wcpay-date-filter__operator-select"
		value={ value }
		onChange={ ( event ) => onChange( event.target.value as DateOperator ) }
	>
		{ operators.map( ( op ) => (
			<option key={ op } value={ op }>
				{ operatorLabel( op ) }
			</option>
		) ) }
	</select>
);
