/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { CheckboxControl } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import interpolateComponents from 'interpolate-components';

const EnabledCurrenciesModalCheckbox = ( {
	onChange,
	checked = false,
	currency: { flag, symbol, code, name },
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
				label={ interpolateComponents( {
					mixedString: '{{flag /}} {{name /}} {{code /}}',
					components: {
						flag: (
							<span className="enabled-currency-checkbox__flag">
								{ '' !== flag ? (
									flag
								) : (
									<div className="enabled-currency-checkbox__flag-text">
										<span>{ code }</span>
									</div>
								) }
							</span>
						),
						name: <span>{ name }</span>,
						code: (
							<span className="enabled-currency-checkbox__code">
								({ symbol }
								{ symbol !== code && ' ' + code })
							</span>
						),
					},
				} ) }
			/>
		</li>
	);
};

export default EnabledCurrenciesModalCheckbox;
