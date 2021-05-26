/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { CheckboxControl } from '@wordpress/components';
import { useCallback } from '@wordpress/element';

const EnabledCurrenciesModalCheckbox = ( {
	code,
	flag,
	onChange,
	name,
	checked = false,
} ) => {
	const handleChange = useCallback(
		( enabled ) => {
			onChange( code, enabled );
		},
		[ code, onChange ]
	);

	return (
		<li className="enabled-currency-checkbox">
			<CheckboxControl
				code={ code }
				checked={ checked }
				onChange={ handleChange }
				label={ `${ flag } ${ name }` }
			/>
		</li>
	);
};

export default EnabledCurrenciesModalCheckbox;
