/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */

import './../style.scss';

const FraudProtectionRuleDescription = ( { children } ) => {
	return (
		<div className="fraud-protection-rule-description">
			<strong>How does this filter protect me?</strong>
			<p>{ children }</p>
		</div>
	);
};

export default FraudProtectionRuleDescription;
