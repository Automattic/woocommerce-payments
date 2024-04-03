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

const PaymentActivity: React.FC = () => {
	const isOverviewSurveySubmitted =
		wcpaySettings.isOverviewSurveySubmitted ?? false;
	return (
		<Card>
			<CardHeader>
				{ __( 'Your payment activity', 'woocommerce-payments' ) }
				{ /* Filters go here */ }
			</CardHeader>
			<CardBody>
				<>{ /* Sub components go here */ }</>
			</CardBody>
			{ ! isOverviewSurveySubmitted && (
				<CardFooter size="extraSmall">
					<WcPayOverviewSurveyContextProvider>
						<Survey />
					</WcPayOverviewSurveyContextProvider>
				</CardFooter>
			) }
		</Card>
	);
};

export default PaymentActivity;
