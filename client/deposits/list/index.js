/** @format **/

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { TableCard, Link } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies.
 */
import { useDeposits } from 'data';
import { displayType, displayStatus } from '../strings';
import { formatStringValue } from 'util';
import DetailsLink, { getDetailsURL } from 'components/details-link';
import ClickableCell from 'components/clickable-cell';

const currency = new Currency();

// TODO make date, amount sortable - when date is sortable, the background of the info buttons should match
const columns = [
	{ key: 'details', label: '', required: true, cellClassName: 'info-button' },
	{
		key: 'date',
		label: __( 'Date', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
		defaultOrder: 'desc',
		cellClassName: 'date-time',
	},
	{
		key: 'type',
		label: __( 'Type', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'amount',
		label: __( 'Amount', 'woocommerce-payments' ),
		isNumeric: true,
		required: true,
	},
	{
		key: 'status',
		label: __( 'Status', 'woocommerce-payments' ),
		required: true,
	},
	// TODO { key: 'transactions', label: __( 'Transactions', 'woocommerce-payments' ), isNumeric: true },
	{ key: 'bankAccount', label: __( 'Bank account', 'woocommerce-payments' ) },
];

export const DepositsList = () => {
	const { deposits, isLoading } = useDeposits( getQuery() );

	const rows = deposits.map( ( deposit ) => {
		const clickable = ( children ) => (
			<ClickableCell href={ getDetailsURL( deposit.id, 'deposits' ) }>
				{ children }
			</ClickableCell>
		);
		const detailsLink = (
			<DetailsLink id={ deposit.id } parentSegment="deposits" />
		);

		const dateDisplay = (
			<Link href={ getDetailsURL( deposit.id, 'deposits' ) }>
				{ dateI18n( 'M j, Y', moment.utc( deposit.date ) ) }
			</Link>
		);

		// Map deposit to table row.
		const data = {
			details: { value: deposit.id, display: detailsLink },
			date: { value: deposit.date, display: dateDisplay },
			type: {
				value: deposit.type,
				display: clickable( displayType[ deposit.type ] ),
			},
			amount: {
				value: deposit.amount / 100,
				display: clickable(
					currency.formatCurrency( deposit.amount / 100 )
				),
			},
			status: {
				value: deposit.status,
				display: clickable(
					displayStatus[ deposit.status ] ||
						formatStringValue( deposit.status )
				),
			},
			bankAccount: {
				value: deposit.bankAccount,
				display: clickable( deposit.bankAccount ),
			},
		};

		return columns.map( ( { key } ) => data[ key ] || { display: null } );
	} );

	return (
		<TableCard
			// className="deposits-list"
			title={ __( 'Deposit history', 'woocommerce-payments' ) }
			isLoading={ isLoading }
			// rowsPerPage={ getQuery().per_page || 25 }
			// totalRows={ count || 0 }
			rowsPerPage={ 10 }
			totalRows={ 10 }
			headers={ columns }
			rows={ rows }
			query={ getQuery() }
			onQueryChange={ onQueryChange }
		/>
	);
};

export default DepositsList;
