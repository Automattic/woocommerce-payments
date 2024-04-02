/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const PaymentActivity: React.FC = () => {
	return (
		<Card>
			<CardHeader>
				{ __( 'Your payment activity', 'woocommerce-payments' ) }
				{ /* Filters go here */ }
			</CardHeader>
			<CardBody>
				<>{ /* Sub components go here */ }</>
			</CardBody>
		</Card>
	);
};

export default PaymentActivity;
