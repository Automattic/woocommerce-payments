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
import './style.scss';

const PaymentActivity: React.FC = () => {
	const { all_time_tpv: allTimeTPV } = wcpaySettings;
	const hasAtLeastOnePayment = allTimeTPV !== 0; // placeholder for testing.

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
		</Card>
	);
};

export default PaymentActivity;
