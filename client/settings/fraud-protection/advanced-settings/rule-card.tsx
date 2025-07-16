/**
 * External dependencies
 */
import React from 'react';
import { Card } from 'wcpay/components/wp-components-wrapped';

/**
 * Internal dependencies
 */
import './../style.scss';
import CardBody from '../../card-body';

interface FraudProtectionRuleCardProps {
	title: string;
	id: string;
}

const FraudProtectionRuleCard: React.FC< React.PropsWithChildren<
	FraudProtectionRuleCardProps
> > = ( { title, children, id } ) => {
	return (
		<Card id={ id } className="fraud-protection-rule-card">
			<CardBody className="wcpay-card-body">
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
