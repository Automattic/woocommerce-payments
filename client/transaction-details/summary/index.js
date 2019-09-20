/** @format **/

/**
 * External dependencies
 */
import { Card } from '@woocommerce/components';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { get } from 'lodash';

/**
 * Internal dependencies.
 */
import OrderLink from '../../components/order-link';
import CardSummary from '../../components/card-summary';
import HorizontalList from '../../components/horizontal-list';

const TransactionSummaryDetails = ( props ) => {
	const { transaction } = props;
	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Summary" action={ transaction.id }>
			Summary details for transaction { transaction.id }.
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
