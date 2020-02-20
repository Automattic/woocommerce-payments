/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { Button } from '@wordpress/components';
import { Card } from '@woocommerce/components';
import Currency from '@woocommerce/currency';
import moment from 'moment';
import { get } from 'lodash';

/**
 * Internal dependencies.
 */
import { getChargeAmounts } from '../../utils/charge';
import PaymentStatusChip from '../../components/payment-status-chip';
import PaymentMethodDetails from '../../components/payment-method-details';
import HorizontalList from '../../components/horizontal-list';
import './style.scss';

const currency = new Currency();

const placeholderValues = {
	net: 0,
	fee: 0,
	refunded: null,
};

const PaymentDetailsSummary = ( props ) => {
	const { charge } = props;
	const { net, fee, refunded } = ( charge.amount ? getChargeAmounts( charge ) : placeholderValues );

	return (
		<Card className="payment-details-summary-details">
			<div className="payment-details-summary">
				<div className="payment-details-summary__section">
					<p className="payment-details-summary__amount">
						{ currency.formatCurrency( ( charge.amount || 0 ) / 100 ) }
						<span className="payment-details-summary__amount-currency">{ ( charge.currency || 'cur' ) }</span>
						<PaymentStatusChip charge={ charge } />
					</p>
					<div className="payment-details-summary__breakdown">
						{ refunded
							? <p>
								{ `${ __( 'Refunded', 'woocommerce-payments' ) }: ` }
								{ currency.formatCurrency( -refunded / 100 ) }
							</p>
							: '' }
						<p>
							{ `${ __( 'Fee', 'woocommerce-payments' ) }: ` }
							{ currency.formatCurrency( -fee / 100 ) }
						</p>
						<p>
							{ `${ __( 'Net', 'woocommerce-payments' ) }: ` }
							{ currency.formatCurrency( net / 100 ) }
						</p>
					</div>
				</div>
				<div className="payment-details-summary__section">
					{ /* TODO: implement control buttons depending on the transaction status */ }
					<div className="payment-details-summary__actions">
						{ charge.order
							? <Button className="payment-details-summary__actions-item"
								isDefault
								isLarge
								href={ charge.order.url }>
									{ `${ __( 'View order' ) } ${ charge.order.number }` }
							</Button>
							: '' }
					</div>
				</div>
			</div>
			<hr className="full-width" />
			<HorizontalList items={ [
				{
					title: __( 'Date', 'woocommerce-payments' ),
					content: charge.created ? dateI18n( 'M j, Y, g:ia', moment( charge.created * 1000 ) ) : '–',
				},
				{
					title: __( 'Customer', 'woocommerce-payments' ),
					content: get( charge, 'billing_details.name' ) || '–',
				},
				{
					title: __( 'Payment Method', 'woocommerce-payments' ),
					content: <PaymentMethodDetails payment={ charge.payment_method_details } />,
				},
				{
					title: __( 'Risk Evaluation', 'woocommerce-payments' ),
					content: get( charge, 'outcome.risk_level' ) || '–',
				},
				{
					content: charge.id || '–',
				},
			] } />
		</Card>
	);
};

export default PaymentDetailsSummary;
