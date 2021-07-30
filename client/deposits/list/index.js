/** @format **/

/**
 * External dependencies
 */
import { useMemo } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { __, _n } from '@wordpress/i18n';
import moment from 'moment';
import { TableCard, Link } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies.
 */
import { useDeposits, useDepositsSummary } from 'wcpay/data';
import { displayType, displayStatus } from '../strings';
import { formatStringValue } from 'util';
import { formatExplicitCurrency } from 'utils/currency';
import DetailsLink, { getDetailsURL } from 'components/details-link';
import ClickableCell from 'components/clickable-cell';
import Page from '../../components/page';
import DepositsFilters from '../filters';

import './style.scss';

const getColumns = ( sortByDate ) => [
	{
		key: 'details',
		label: '',
		required: true,
		cellClassName: 'info-button ' + ( sortByDate ? 'is-sorted' : '' ),
	},
	{
		key: 'date',
		label: __( 'Date', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Date', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
		defaultOrder: 'desc',
		cellClassName: 'date-time',
		isSortable: true,
		defaultSort: true,
	},
	{
		key: 'type',
		label: __( 'Type', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Type', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'amount',
		label: __( 'Amount', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Amount', 'woocommerce-payments' ),
		isNumeric: true,
		required: true,
		isSortable: true,
	},
	{
		key: 'status',
		label: __( 'Status', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Status', 'woocommerce-payments' ),
		required: true,
	},
	// TODO { key: 'transactions', label: __( 'Transactions', 'woocommerce-payments' ), isNumeric: true },
	{
		key: 'bankAccount',
		label: __( 'Bank account', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Bank account', 'woocommerce-payments' ),
	},
];

export const DepositsList = () => {
	const { deposits, isLoading } = useDeposits( getQuery() );
	const { depositsSummary, isLoading: isSummaryLoading } = useDepositsSummary(
		getQuery()
	);

	const sortByDate = ! getQuery().orderby || 'date' === getQuery().orderby;
	const columns = useMemo( () => getColumns( sortByDate ), [ sortByDate ] );

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
				{ dateI18n(
					'M j, Y',
					moment.utc( deposit.date ).toISOString(),
					true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
				) }
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
					formatExplicitCurrency( deposit.amount, deposit.currency )
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

	const isCurrencyFiltered = 'string' === typeof getQuery().store_currency_is;
	const isSingleCurrency =
		2 > ( depositsSummary.store_currencies || [] ).length;

	// initializing summary with undefined as we don't want to render the TableSummary component unless we have the data
	let summary;
	const isDespositsSummaryDataLoaded =
		depositsSummary.count !== undefined &&
		depositsSummary.total !== undefined &&
		false === isSummaryLoading;

	// Generate summary only if the data has been loaded
	if ( isDespositsSummaryDataLoaded ) {
		summary = [
			{
				label: _n(
					'deposit',
					'deposits',
					depositsSummary.count,
					'woocommerce-payments'
				),
				value: `${ depositsSummary.count }`,
			},
		];

		if ( isSingleCurrency || isCurrencyFiltered ) {
			summary.push( {
				label: __( 'total', 'woocommerce-payments' ),
				value: `${ formatExplicitCurrency(
					depositsSummary.total,
					depositsSummary.currency
				) }`,
			} );
		}
	}

	const storeCurrencies =
		depositsSummary.store_currencies ||
		( isCurrencyFiltered ? [ getQuery().store_currency_is ] : [] );

	return (
		<Page>
			<DepositsFilters storeCurrencies={ storeCurrencies } />
			<TableCard
				className="wcpay-deposits-list woocommerce-report-table"
				title={ __( 'Deposit history', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ getQuery().per_page || 25 }
				totalRows={ depositsSummary.count }
				headers={ columns }
				rows={ rows }
				summary={ summary }
				query={ getQuery() }
				onQueryChange={ onQueryChange }
			/>
		</Page>
	);
};

export default DepositsList;
