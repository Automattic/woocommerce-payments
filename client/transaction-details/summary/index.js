/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { get } from 'lodash';
import { formatCurrency } from '@woocommerce/currency';

/**
 * Internal dependencies.
 */
import PaymentStatusChip from '../../components/payment-status-chip';
import OrderLink from '../../components/order-link';
import CardSummary from '../../components/card-summary';
import HorizontalList from '../../components/horizontal-list';
import './style.scss';

const TransactionSummaryDetails = ( props ) => {
	const { transaction } = props;
	// TODO: this is a placeholder card and does not require translation
	return (
		<Card>
			<div className="transaction-summary">
				<div className="transaction-summary__section">
					<h1 className="transaction-summary__amount">
						{ formatCurrency( ( transaction.amount || 0 ) / 100 ) }
						<span className="transaction-summary__amount-currency">{ ( transaction.currency || 'cur' ) }</span>
						<PaymentStatusChip transaction={ transaction } style={ { marginLeft: '1em' } } />
					</h1>
					<div className="transaction-summary__breakdown">
						{ transaction.dispute
							? <p>Dispute: { formatCurrency( ( get( transaction, 'dispute.amount' ) || 0 ) / 100 ) }</p>
							: '' }
						<p>Fee: { formatCurrency( ( -transaction.fee || 0 ) / 100 ) }</p>
						<p>Net: { formatCurrency( ( transaction.net || 0 ) / 100 ) }</p>
					</div>
				</div>
				<div className="transaction-summary__section">
					{ /* TODO: implement control buttons depending on the transaction status */ }
					{ /* E.g. if transaction is under dispute display Accept Dispute and Respond to Dispute buttons */ }
				</div>
			</div>
			<hr style={ { margin: '0 -16px' /* Accounting for woocommerce-card__body padding */ } } />
			<HorizontalList items={ [
				{ title: 'Date', description: dateI18n( 'M j, Y, g:ia', moment( ( transaction.created || 0 ) * 1000 ) ) },
				{ title: 'Order No.', description: <OrderLink order={ transaction.order } /> },
				{ title: 'Customer', description: get( transaction, 'billing_details.name' ) || 'Sample customer' },
				{ title: 'Payment Method', description: <CardSummary card={ get( transaction, 'source.payment_method_details.card' ) } /> },
				{ title: 'Risk Evaluation', description: get( transaction, 'source.outcome.risk_level' ) || '–' },
			] } />
		</Card>
	);
};

export default TransactionSummaryDetails;
