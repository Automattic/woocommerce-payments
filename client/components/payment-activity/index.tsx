/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import PaymentsActivityData from './payments-activity-data';

/**
 * Internal dependencies.
 */
import './style.scss';

const PaymentActivity: React.FC = () => {
	return (
		<Card>
			<CardHeader>
				{ __( 'Your payment activity', 'woocommerce-payments' ) }
				{ /* Filters go here */ }
			</CardHeader>
			<CardBody className="wcpay-payments-activity__card__body">
				<PaymentsActivityData />
			</CardBody>
		</Card>
	);
};

export default PaymentActivity;
