/**
 * External dependencies
 */
import React from 'react';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './../style.scss';
import CardBody from '../../card-body';

interface FraudProtectionRuleCardProps {
	title: string;
	description: React.ReactNode;
	id: string;
}

const FraudProtectionRuleCard: React.FC< FraudProtectionRuleCardProps > = ( {
	title,
	description,
	children,
	id,
} ) => {
	return (
		<Card id={ id } className="fraud-protection-rule-card">
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
