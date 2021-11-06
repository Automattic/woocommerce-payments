/** @format */

/**
 * External Dependencies
 */
import React, { useState } from 'react';
import { CheckboxControl } from '@wordpress/components';
import './style.scss';

const LoadableCheckboxControl = ( {
	id,
	key,
	label,
	onChange,
	disabled,
	checked,
	heading,
	delayMs = 1500,
} ) => {
	const [ isLoading, setLoading ] = useState( false );
	const handleOnChange = ( status ) => {
		setLoading( true );
		setTimeout( () => {
			onChange( status );
			setLoading( false );
		}, delayMs );
	};

	return (
		<>
			{ isLoading && (
				<div className={ 'loadable-checkbox__spinner' }>
					<svg
						width="131px"
						height="131px"
						viewBox="0 0 100 100"
						preserveAspectRatio="xMidYMid"
					>
						<circle
							cx="50"
							cy="50"
							fill="none"
							stroke="#ffffff"
							strokeWidth="12"
							r="32"
							strokeDasharray="150.79644737231007 52.26548245743669"
						>
							<animateTransform
								attributeName="transform"
								type="rotate"
								repeatCount="indefinite"
								dur="1.4492753623188404s"
								values="0 50 50;360 50 50"
								keyTimes="0;1"
							></animateTransform>
						</circle>
					</svg>
				</div>
			) }
			<CheckboxControl
				id={ id }
				key={ key }
				label={ label }
				onChange={ handleOnChange }
				disabled={ disabled }
				checked={ checked }
				heading={ heading }
			/>
		</>
	);
};

export default LoadableCheckboxControl;
