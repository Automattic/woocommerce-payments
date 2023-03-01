/**
 * External dependencies
 */
import { CheckboxControl, Icon, ToggleControl } from '@wordpress/components';
import React, { useState } from 'react';

/**
 * Internal dependencies
 */

import './../style.scss';

const FraudProtectionRuleToggle = ( { key, label, helpText } ) => {
	const [ toggleState, setToggleState ] = useState( false );
	const [ checkboxState, setCheckboxState ] = useState( false );
	return (
		<div className="fraud-protection-rule-toggle">
			<strong>Enable filtering</strong>
			<ToggleControl
				label={ label }
				key={ key }
				help={ helpText }
				checked={ toggleState }
				className="fraud-protection-rule-toggle-toggle"
				onChange={ () => {
					setToggleState( ! toggleState );
				} }
			></ToggleControl>
			{ toggleState && (
				<div>
					<strong>Advanced</strong>
					<div className="fraud-protection-rule-toggle-checkbox-container">
						<CheckboxControl
							label="Block Payment"
							className="fraud-protection-rule-toggle-checkbox"
							checked={ checkboxState }
							onChange={ () => {
								setCheckboxState( ! checkboxState );
							} }
						></CheckboxControl>
						<Icon
							icon={
								<svg
									width="16"
									height="16"
									viewBox="3 3 18 18"
									fill="none"
									xmlns="http://www.w3.org/2000/svg"
								>
									<path
										d={
											'M9.75 10.25C9.75 9.00736 10.7574 8 12 8C13.2426 8 14.25 9.00736 14.25 10.25C14.25 11.4083 ' +
											'13.3748 12.3621 12.2496 12.4863C12.1124 12.5015 12 12.6119 12 12.75V14M12 15V16.5M20 12C20 ' +
											'16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12C4 7.58172 7.58172 4 12 4C16.4183 ' +
											'4 20 7.58172 20 12Z'
										}
										stroke="#1E1E1E"
										strokeWidth="1.5"
									/>
								</svg>
							}
						></Icon>
					</div>
				</div>
			) }
		</div>
	);
};

export default FraudProtectionRuleToggle;
