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
import {
	isTransactionRefunded,
	isTransactionPartiallyRefunded,
	isTransactionFullyRefunded,
} from '../../utils/transaction';
import PaymentStatusChip from '../../components/payment-status-chip';
import OrderLink from '../../components/order-link';
import PaymentMethodDetails from '../../components/payment-method-details';
import HorizontalList from '../../components/horizontal-list';
import './style.scss';

const currency = new Currency();

const TransactionSummaryDetails = ( props ) => {
	const { transaction } = props;
	return (
		<Card className="transaction-summary-details">
			<div className="transaction-summary">
				<div className="transaction-summary__section">
					<p className="transaction-summary__amount">
						{ currency.formatCurrency( ( transaction.amount || 0 ) / 100 ) }
						<span className="transaction-summary__amount-currency">{ ( transaction.currency || 'cur' ) }</span>
						<PaymentStatusChip transaction={ transaction } />
					</p>
					<div className="transaction-summary__breakdown">
						{ isTransactionRefunded( transaction )
							? <p>
								{ `${ __( 'Refunded', 'woocommerce-payments' ) }: ` }
								{ currency.formatCurrency( ( -get( transaction, 'source.amount_refunded' ) || 0 ) / 100 ) }
							</p>
							: '' }
						<p>
							{ `${ __( 'Fee', 'woocommerce-payments' ) }: ` }
							{ currency.formatCurrency( ( -transaction.fee || 0 ) / 100 ) }
						</p>
						<p>
							{ `${ __( 'Net', 'woocommerce-payments' ) }: ` }
							{ currency.formatCurrency( ( transaction.net || 0 ) / 100 ) }
						</p>
					</div>
				</div>
				<div className="transaction-summary__section">
					{ /* TODO: implement control buttons depending on the transaction status */ }
					{ /* E.g. if transaction is under dispute display Accept Dispute and Respond to Dispute buttons */ }
					<div className="transaction-summary__actions">
						<Button className="transaction-summary__actions-item"
							isDefault
							isLarge
							disabled={ ! get( transaction, 'order.url' ) ||	isTransactionFullyRefunded( transaction ) }
							href={ `${ get( transaction, 'order.url' ) }#woocommerce-order-items` }>
							{ ( isTransactionPartiallyRefunded( transaction ) )
								? __( 'Refund more', 'woocommerce-payments' )
								: __( 'Refund', 'woocommerce-payments' ) }
						</Button>
					</div>
				</div>
			</div>
			<hr className="full-width" />
			<HorizontalList items={ [
				{
					title: __( 'Date', 'woocommerce-payments' ),
					content: transaction.created ? dateI18n( 'M j, Y, g:ia', moment( transaction.created * 1000 ) ) : '–',
				},
				{
					title: __( 'Order No.', 'woocommerce-payments' ),
					content: <OrderLink order={ transaction.order } />,
				},
				{
					title: __( 'Customer', 'woocommerce-payments' ),
					content: get( transaction, 'source.billing_details.name' ) || '–',
				},
				{
					title: __( 'Payment Method', 'woocommerce-payments' ),
					content: <PaymentMethodDetails payment={ get( transaction, 'source.payment_method_details' ) } />,
				},
				{
					title: __( 'Risk Evaluation', 'woocommerce-payments' ),
					content: get( transaction, 'source.outcome.risk_level' ) || '–',
				},
				{
					content: transaction.id || '–',
				},
			] } />
		</Card>
	);
};

export default TransactionSummaryDetails;
