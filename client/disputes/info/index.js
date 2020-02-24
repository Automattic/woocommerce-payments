/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import OrderLink from 'components/order-link';
import { getDetailsURL } from 'components/details-link';
import { reasons } from '../strings';
import { formatStringValue } from '../../util';
import './style.scss';

const currency = new Currency();

const fields = [
	{ key: 'created', label: __( 'Dispute date', 'woocommerce-payments' ) },
	{ key: 'amount', label: __( 'Disputed amount', 'woocommerce-payments' ) },
	{ key: 'dueBy', label: __( 'Respond by', 'woocommerce-payments' ) },
	{ key: 'reason', label: __( 'Reason', 'woocommerce-payments' ) },
	{ key: 'order', label: __( 'Order', 'woocommerce-payments' ) },
	{ key: 'customer', label: __( 'Customer', 'woocommerce-payments' ) },
	{ key: 'transactionId', label: __( 'Transaction ID', 'woocommerce-payments' ) },
];

const Info = ( { dispute } ) => {
	const reasonMapping = reasons[ dispute.reason ];
	const reasonDisplay = reasonMapping ? reasonMapping.display : formatStringValue( dispute.reason );

	const chargeId = typeof dispute.charge === 'object' ? dispute.charge.id : dispute.charge;
	const transactionIdDisplay = (
		<Link href={ getDetailsURL( chargeId, 'transactions' ) } >{ chargeId }</Link>
	);

	const data = {
		created: dateI18n( 'M j, Y', moment( dispute.created * 1000 ) ),
		amount: `${ currency.formatCurrency( dispute.amount / 100 ) } ${ currency.code }`,
		dueBy: dateI18n( 'M j, Y - g:iA', moment( dispute.evidence_details.due_by * 1000 ) ),
		reason: reasonDisplay,
		order: dispute.order ? <OrderLink order={ dispute.order } /> : null,
		customer: typeof dispute.charge === 'object' ? dispute.charge.billing_details.name : null,
		transactionId: transactionIdDisplay,
	};

	return (
		<div className="wcpay-dispute-info">
			{ fields.map( ( { key, label } ) => (
				data[ key ] == null ? null : (
					<div key={ key }>
						<span className="wcpay-dispute-info-key">{ `${ label }: ` }</span>
						<span className="wcpay-dispute-info-value">{ data[ key ] }</span>
					</div>
				)
			) ) }
		</div>
	);
};

export default Info;
