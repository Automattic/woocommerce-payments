/** @format **/

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import { TableCard } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies.
 */
import { useDisputes } from 'wcpay/data';
import OrderLink from 'components/order-link';
import DisputeStatusChip from 'components/dispute-status-chip';
import ClickableCell from 'components/clickable-cell';
import DetailsLink, { getDetailsURL } from 'components/details-link';
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import { reasons } from './strings';
import { formatStringValue } from 'utils';
import { formatExplicitCurrency } from 'utils/currency';

import './style.scss';

const headers = [
	{ key: 'details', label: '', required: true, cellClassName: 'info-button' },
	{
		key: 'amount',
		label: __( 'Amount', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'status',
		label: __( 'Status', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'reason',
		label: __( 'Reason', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'source',
		label: __( 'Source', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'order',
		label: __( 'Order #', 'woocommerce-payments' ),
		required: true,
	},
	{ key: 'customer', label: __( 'Customer', 'woocommerce-payments' ) },
	{
		key: 'email',
		label: __( 'Email', 'woocommerce-payments' ),
		visible: false,
	},
	{
		key: 'country',
		label: __( 'Country', 'woocommerce-payments' ),
		visible: false,
	},
	{
		key: 'created',
		label: __( 'Disputed on', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'dueBy',
		label: __( 'Respond by', 'woocommerce-payments' ),
		required: true,
	},
];

export const DisputesList = () => {
	const { disputes, isLoading } = useDisputes( getQuery() );

	const rows = disputes.map( ( dispute ) => {
		const order = dispute.order
			? {
					value: dispute.order.number,
					display: <OrderLink order={ dispute.order } />,
			  }
			: null;

		const clickable = ( children ) => (
			<ClickableCell href={ getDetailsURL( dispute.id, 'disputes' ) }>
				{ children }
			</ClickableCell>
		);

		const detailsLink = (
			<DetailsLink id={ dispute.id } parentSegment="disputes" />
		);

		const reasonMapping = reasons[ dispute.reason ];
		const reasonDisplay = reasonMapping
			? reasonMapping.display
			: formatStringValue( dispute.reason );

		const charge = dispute.charge || {};
		const source = ( ( charge.payment_method_details || {} ).card || {} )
			.brand;
		const customer = charge.billing_details || {};

		const data = {
			amount: {
				value: dispute.amount / 100,
				display: clickable(
					formatExplicitCurrency(
						dispute.amount || 0,
						dispute.currency || 'USD'
					)
				),
			},
			status: {
				value: dispute.status,
				display: clickable(
					<DisputeStatusChip status={ dispute.status } />
				),
			},
			reason: {
				value: dispute.reason,
				display: clickable( reasonDisplay ),
			},
			source: {
				value: source,
				display: clickable(
					<span
						className={ `payment-method__brand payment-method__brand--${ source }` }
					/>
				),
			},
			created: {
				value: dispute.created * 1000,
				display: clickable(
					dateI18n(
						'M j, Y',
						moment( dispute.created * 1000 ).toISOString()
					)
				),
			},
			dueBy: {
				value: dispute.evidence_details.due_by * 1000,
				display: clickable(
					dateI18n(
						'M j, Y / g:iA',
						moment(
							dispute.evidence_details.due_by * 1000
						).toISOString()
					)
				),
			},
			order,
			customer: {
				value: customer.name,
				display: clickable( customer.name ),
			},
			email: {
				value: customer.email,
				display: clickable( customer.email ),
			},
			country: {
				value: ( customer.address || {} ).country,
				display: clickable( ( customer.address || {} ).country ),
			},
			details: { value: dispute.id, display: detailsLink },
		};

		return headers.map( ( { key } ) => data[ key ] || { display: null } );
	} );

	return (
		<Page>
			<TestModeNotice topic={ topics.disputes } />
			<TableCard
				className="wcpay-disputes-list"
				title={ __( 'Disputes', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ 10 }
				totalRows={ 10 }
				headers={ headers }
				rows={ rows }
				query={ getQuery() }
				onQueryChange={ onQueryChange }
			/>
		</Page>
	);
};

export default DisputesList;
