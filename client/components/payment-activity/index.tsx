/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies.
 */
import EmptyStateAsset from 'assets/images/empty-activity-state.svg?asset';

import './style.scss';

const PaymentActivity: React.FC = () => {
	const {
		transactions_data: { tpv },
	} = wcpaySettings;

	return (
		<Card className="">
			<CardHeader>
				{ __( 'You payment activity', 'woocommerce-payments' ) }
			</CardHeader>
			<CardBody className="wcpay-payments-activity__card__body">
				{ tpv === 0 && (
					<>
						<img src={ EmptyStateAsset } alt="" />
						<p>
							{ interpolateComponents( {
								mixedString: sprintf(
									__(
										'{{strong}}No payments… yet!{{/strong}}',
										'woocommerce-payments'
									),
									'WooPayments'
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
					</>
				) }
				{ /* This should be replaced with the correct graphs */ }
				{ tpv !== 0 && <>You have some money</> }
			</CardBody>
		</Card>
	);
};

export default PaymentActivity;
