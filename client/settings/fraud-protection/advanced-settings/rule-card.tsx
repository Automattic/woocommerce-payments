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
	id: string;
}

const FraudProtectionRuleCard: React.FC< FraudProtectionRuleCardProps > = ( {
	title,
	children,
	id,
} ) => {
	return (
		<Card id={ id } className="fraud-protection-rule-card">
			<CardBody>
				<div>
					<p className="fraud-protection-rule-card-header">
						{ title }
					</p>
				</div>
				{ children }
			</CardBody>
		</Card>
	);
};

export default FraudProtectionRuleCard;
