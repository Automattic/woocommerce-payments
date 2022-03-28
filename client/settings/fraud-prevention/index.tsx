/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import { useFraudProtection } from '../../data';

const FraudPrevention = (): JSX.Element => {
	const [ isFraudProtectionEnabled, setIsFraudProtectionEnabled ]: [
		boolean,
		( newState: boolean ) => void
	] = useFraudProtection() as [ boolean, ( newState: boolean ) => void ];

	return (
		<Card className="fraud-prevention">
			<CardBody>
				<CheckboxControl
					checked={ isFraudProtectionEnabled }
					onChange={ setIsFraudProtectionEnabled }
					label={ __(
						'Enable fraud prevention',
						'woocommerce-payments'
					) }
					help={ __(
						'When enabled, we will only ask your customers to complete a CAPTCHA when we detect suspicious activity in your store.',
						'woocommerce-payments'
					) }
				/>
			</CardBody>
		</Card>
	);
};

export default FraudPrevention;
