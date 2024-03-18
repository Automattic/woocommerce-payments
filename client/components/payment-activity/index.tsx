/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import './style.scss';

const PaymentActivity: React.FC = () => {
	return (
		<Card className="wcpay-payments-activity__card">
			<CardHeader
				className="wcpay-payments-activity__card__header"
				isBorderless={ true }
			>
				{ __( 'Your payment activity', 'woocommerce-payments' ) }
				{ /* Filters go here */ }
			</CardHeader>
			<CardBody className="wcpay-payments-activity__card__body">
				<>{ /* Sub components go here */ }</>
			</CardBody>
		</Card>
	);
};

export default PaymentActivity;
