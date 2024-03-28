/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardBody, CardFooter, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Survey from './survey';
import { WcPayOverviewSurveyContextProvider } from './survey/context';
import './style.scss';

const PaymentActivity: React.FC = () => {
	const isOverviewSurveySubmitted =
		wcpaySettings.isOverviewSurveySubmitted ?? false;
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
			{ ! isOverviewSurveySubmitted && (
				<CardFooter>
					<WcPayOverviewSurveyContextProvider>
						<Survey />
					</WcPayOverviewSurveyContextProvider>
				</CardFooter>
			) }
		</Card>
	);
};

export default PaymentActivity;
