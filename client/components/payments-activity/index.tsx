/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */

import EmptyStateAsset from 'assets/images/payment-activity-empty-state.svg?asset';
import interpolateComponents from '@automattic/interpolate-components';
import PaymentsActivityData from './payments-activity-data';
import Survey from './survey';
import { WcPayOverviewSurveyContextProvider } from './survey/context';
import './style.scss';

const PaymentsActivity: React.FC = () => {
	const { lifetimeTPV } = wcpaySettings;
	const hasAtLeastOnePayment = lifetimeTPV > 0;
	const isOverviewSurveySubmitted =
		wcpaySettings.isOverviewSurveySubmitted ?? false;

	return (
		<Card>
			<CardHeader>
				{ __( 'Your payment activity', 'woocommerce-payments' ) }

				{ hasAtLeastOnePayment && <>{ /* Filters go here */ }</> }
			</CardHeader>
			<CardBody className="wcpay-payments-activity__card__body">
				{ hasAtLeastOnePayment ? (
					<>
						{ /* Total TPV tile goes here */ }
						{ /* Charges, refunds, disputes, fees tiles go here */ }
						<PaymentsActivityData />
					</>
				) : (
					<div className="wcpay-payments-activity__card__body__empty-state-wrapper">
						<img src={ EmptyStateAsset } alt="" />
						<p>
							{ interpolateComponents( {
								mixedString: __(
									'{{strong}}No payments…yet!{{/strong}}'
								),
								components: {
									strong: <strong />,
								},
							} ) }
						</p>
						<p>
							{ __(
								"Once your first order comes in, you'll start seeing your payment activity right here.",
								'woocommerce-payments'
							) }
						</p>
					</div>
				) }
			</CardBody>

			{ ! isOverviewSurveySubmitted && (
				<WcPayOverviewSurveyContextProvider>
					<Survey />
				</WcPayOverviewSurveyContextProvider>
			) }
		</Card>
	);
};

export default PaymentsActivity;
