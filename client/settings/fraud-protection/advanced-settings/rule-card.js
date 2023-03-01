/**
 * External dependencies
 */
import React from 'react';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../../card-body';

import './../style.scss';

const FraudProtectionRuleCard = ( { title, description, children } ) => {
	return (
		<Card className="fraud-protection-rule-card">
			<CardBody className="fraud-protection-rule-card-header-container">
				<div>
					<p className="fraud-protection-rule-card-header">
						{ title }
					</p>
					<p className="fraud-protection-rule-card-description">
						{ description }
					</p>
				</div>
			</CardBody>
			<hr></hr>
			<CardBody>{ children }</CardBody>
		</Card>
	);
};

export default FraudProtectionRuleCard;
