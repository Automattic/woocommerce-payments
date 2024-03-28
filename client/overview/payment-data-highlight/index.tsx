/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { Card } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PaymentDataHighlight from 'components/payment-data-highlight';
import './styles.scss';

const PaymentOverviewDataHighlight = (): JSX.Element => {
	return (
		<Card>
			<div className="payment-overview-data-highlight__wrapper">
				<div className="payment-overview-data-highlight__wrapper-column">
					<PaymentDataHighlight
						label={ __( 'Charges', 'woocommerce-payments' ) }
						amount={ '€3,143.00' }
						change={ 22 }
						reportUrl="#"
						tooltip={ __(
							"A charge is the amount billed to your customer's payment method.",
							'woocommerce-payments'
						) }
					/>
				</div>
				<div className="payment-overview-data-highlight__wrapper-column">
					<PaymentDataHighlight
						label={ __( 'Refunds', 'woocommerce-payments' ) }
						amount={ '€1,532.00' }
						change={ -50 }
						reportUrl="#"
					/>
				</div>
				<div className="payment-overview-data-highlight__wrapper-column">
					<PaymentDataHighlight
						label={ __( 'Disputes', 'woocommerce-payments' ) }
						amount={ '€47.27' }
						change={ 5 }
						reportUrl="#"
					/>
				</div>
				<div className="payment-overview-data-highlight__wrapper-column">
					<PaymentDataHighlight
						label={ __( 'Fees', 'woocommerce-payments' ) }
						amount={ '€94.29' }
						change={ 11 }
						reportUrl="#"
						tooltip={ __(
							'Fees includes fees on payments as well as dusputes.',
							'woocommerce-payments'
						) }
					/>
				</div>
			</div>
		</Card>
	);
};

export default PaymentOverviewDataHighlight;
