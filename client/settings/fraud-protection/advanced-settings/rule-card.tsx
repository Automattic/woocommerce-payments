/**
 * External dependencies
 */
import React from 'react';
import { Card } from 'wcpay/components/wp-components-wrapped/components/card';

/**
 * Internal dependencies
 */
import './rule-card.scss';
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
				<h4>{ title }</h4>
				{ children }
			</CardBody>
		</Card>
	);
};

export default FraudProtectionRuleCard;
