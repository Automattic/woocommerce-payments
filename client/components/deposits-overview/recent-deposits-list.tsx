/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, FlexItem, Icon } from '@wordpress/components';
import { calendar } from '@wordpress/icons';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import strings from './strings';
import './style.scss';
import DepositStatusPill from 'components/deposit-status-pill';
import Loadable from 'components/loadable';
import { getDepositDate } from 'deposits/utils';
import { CachedDeposit } from 'wcpay/types/deposits';
import { formatCurrency } from 'wcpay/utils/currency';
import { getDetailsURL } from 'wcpay/components/details-link';
import useRecentDeposits from './hooks';

interface DepositRowProps {
	deposit: CachedDeposit;
}

interface RecentDepositsProps {
	currency: string;
}

const tableClass = 'wcpay-deposits-overview__table';

/**
 * Renders a recent deposits table row.
 *
 * @return {JSX.Element} Deposit table row.
 */
const DepositTableRow: React.FC< DepositRowProps > = ( {
	deposit,
} ): JSX.Element => {
	return (
		<Flex className={ `${ tableClass }__row` }>
			<FlexItem className={ `${ tableClass }__cell` }>
				<Icon icon={ calendar } size={ 17 } />
				<Link href={ getDetailsURL( deposit.id, 'deposits' ) }>
					{ getDepositDate( deposit ) }
				</Link>
			</FlexItem>
			<FlexItem className={ `${ tableClass }__cell` }>
				<DepositStatusPill status={ deposit.status } />
			</FlexItem>
			<FlexItem className={ `${ tableClass }__cell` }>
				{ formatCurrency( deposit.amount, deposit.currency ) }
			</FlexItem>
		</Flex>
	);
};

/**
 * Renders a recent deposits table row with loading placeholders.
 *
 * @return {JSX.Element} Deposit table row with loading placeholders.
 */
const DepositTableRowLoading: React.FC = (): JSX.Element => {
	return (
		<Flex className={ `${ tableClass }__row` }>
			<FlexItem className={ `${ tableClass }__cell` }>
				<Loadable isLoading placeholder="loading" />
			</FlexItem>
			<FlexItem className={ `${ tableClass }__cell` }>
				<Loadable isLoading placeholder="loading" />
			</FlexItem>
			<FlexItem className={ `${ tableClass }__cell` }>
				<Loadable isLoading placeholder="loading" />
			</FlexItem>
		</Flex>
	);
};

/**
 * Renders the Recent Deposit details component.
 *
 * This component includes the recent deposit heading, table and notice.
 *
 * @param {RecentDepositsProps} props Recent Deposit props.
 * @return {JSX.Element} Rendered element with Next Deposit details.
 */
const RecentDepositsList: React.FC< RecentDepositsProps > = ( {
	currency,
} ): JSX.Element => {
	const { isLoading, deposits } = useRecentDeposits( currency );

	if ( ! isLoading && deposits.length === 0 ) {
		return <></>;
	}

	return (
		<>
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

				{ isLoading && <DepositTableRowLoading /> }

				{ deposits.map( ( deposit ) => (
					<DepositTableRow key={ deposit.id } deposit={ deposit } />
				) ) }
			</div>
		</>
	);
};

export default RecentDepositsList;
