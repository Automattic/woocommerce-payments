/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import React from 'react';

/**
 * Internal dependencies
 */
import './../style.scss';

const FraudProtectionRuleDescription: React.FC = ( { children } ) => {
	return (
		<div className="fraud-protection-rule-description">
			<strong>
				{ __(
					'How does this filter protect me?',
					'woocommerce-payments'
				) }
			</strong>
			<p>{ children }</p>
		</div>
	);
};

export default FraudProtectionRuleDescription;
