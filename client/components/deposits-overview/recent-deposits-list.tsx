/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, FlexItem, Icon } from '@wordpress/components';
import { calendar } from '@wordpress/icons';
import { Link } from '@woocommerce/components';
import InfoOutlineIcon from 'gridicons/dist/info-outline';

/**
 * Internal dependencies.
 */
import strings from './strings';
import './style.scss';
import DepositStatusPill from 'components/deposit-status-pill';
import { getDepositDate } from 'deposits/utils';
import { CachedDeposit } from 'wcpay/types/deposits';
import { formatCurrency } from 'wcpay/utils/currency';
import { getDetailsURL } from 'wcpay/components/details-link';
import useRecentDeposits from './hooks';
import BannerNotice from '../banner-notice';

interface DepositRowProps {
	deposit: CachedDeposit;
}

interface RecentDepositsProps {
	currency: string | undefined;
}

const tableClass = 'wcpay-deposits-overview__table';

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
 * Renders the Recent Deposit details component.
 *
 * This component includes the recent deposit heading, table and notice.
 *
 * @param {RecentDepositsProps} props Recent Deposit props.
 * @return {JSX.Element} Rendered element with Next Deposit details.
 */
const RecentDepositsList: React.FC< RecentDepositsProps > = ( {
	currency,
}: RecentDepositsProps ): JSX.Element => {
	const recentDeposits = useRecentDeposits( currency );
	const isLoading = recentDeposits.isLoading;

	if ( isLoading || recentDeposits.deposits.length === 0 ) {
		return <></>;
	}

	// Add a notice indicating the potential business day delay for pending and in_transit deposits.
	let bannerAdded = false;
	const depositRows = recentDeposits.deposits
		.reverse() // reverse the array so that the oldest pending or in_transit deposit has the notice added to it.
		.map( ( deposit ) => {
			let bannerNotice = null;

			if (
				! bannerAdded &&
				( 'pending' === deposit.status ||
					'in_transit' === deposit.status )
			) {
				bannerNotice = (
					<BannerNotice
						status="info"
						icon={ <InfoOutlineIcon /> }
						children={ strings.notices.businessDayDelay }
						isDismissible={ false }
					/>
				);
				bannerAdded = true;
			}

			return (
				<>
					<DepositTableRow deposit={ deposit } />
					{ bannerNotice }
				</>
			);
		} )
		.reverse(); // reverse the array back to the original order.

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
				{ depositRows }
			</div>
		</>
	);
};

export default RecentDepositsList;
