/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, FlexItem, Icon } from '@wordpress/components';
import { calendar } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import strings from './strings';
import './style.scss';
import DepositStatusChip from 'components/deposit-status-chip';
import { getDepositDate } from 'deposits/utils';
import { CachedDeposit } from 'wcpay/types/deposits';
import { useDeposits } from 'wcpay/data';
import { formatCurrency } from 'wcpay/utils/currency';
import { Link } from '@woocommerce/components';
import { getDetailsURL } from 'wcpay/components/details-link';

interface DepositRowProps {
	deposit: CachedDeposit;
}
interface RecentDeposits {
	deposits: CachedDeposit[];
	isLoading: boolean;
}
interface RecentDepositsProps {
	currency: string | undefined;
}

const tableClass = 'wcpay-deposits-overview__table';

const GetRecentDeposits = ( currency?: string ): RecentDeposits => {
	const query = {
		status_is_not: 'estimated',
		store_currency_is: currency,
		orderby: 'date',
		order: 'desc',
		per_page: '3',
	};
	const deposits = useDeposits( query );

	return {
		deposits: deposits.deposits,
		isLoading: deposits.isLoading,
	};
};

const DepositTableRow: React.FC< DepositRowProps > = ( {
	deposit,
}: DepositRowProps ): JSX.Element => {
	return (
		<Flex className={ `${ tableClass }__row` }>
			<FlexItem className={ `${ tableClass }__cell` }>
				<Icon icon={ calendar } size={ 17 } />
				<Link href={ getDetailsURL( deposit.id, 'deposits' ) }>
					{ getDepositDate( deposit ) }
				</Link>
			</FlexItem>
			<FlexItem className={ `${ tableClass }__cell` }>
				<DepositStatusChip status={ deposit.status } isCompact />
			</FlexItem>
			<FlexItem className={ `${ tableClass }__cell` }>
				{ formatCurrency( deposit.amount, deposit.currency ) }
			</FlexItem>
		</Flex>
	);
};

/**
 * Renders the Recent Deposit details component.
 *
 * This component includes the recent deposit heading, table and notice.
 *
 * @param {DepositRowProps} props Next Deposit details props.
 * @return {JSX.Element} Rendered element with Next Deposit details.
 */
const RecentDeposits: React.FC< RecentDepositsProps > = ( {
	currency,
}: RecentDepositsProps ): JSX.Element => {
	const recentDeposits = GetRecentDeposits( currency );
	const isLoading = recentDeposits.isLoading;

	if ( isLoading || recentDeposits.deposits.length === 0 ) {
		return <></>;
	}
	return (
		<>
			{ /* Next Deposit Heading */ }
			<div className="wcpay-deposits-overview__heading">
				<span className="wcpay-deposits-overview__heading__title">
					{ strings.recentDeposits.title }
				</span>

				<span className="wcpay-deposits-overview__heading__description">
					{ strings.recentDeposits.description }
				</span>
			</div>
			{ /* Next Deposit Table */ }
			<div className={ tableClass }>
				<Flex className={ `${ tableClass }__row__header` }>
					<FlexItem className={ `${ tableClass }__cell` }>
						{ strings.tableHeaders.recentDepositDate }
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						{ strings.tableHeaders.status }
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						{ strings.tableHeaders.amount }
					</FlexItem>
				</Flex>
				{ recentDeposits.deposits.map( ( deposit ) => (
					// eslint-disable-next-line react/jsx-key
					<DepositTableRow deposit={ deposit } />
				) ) }
			</div>
		</>
	);
};

export default RecentDeposits;
