/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, FlexItem, Icon } from '@wordpress/components';
import { calendar } from '@wordpress/icons';
import { Link } from '@woocommerce/components';
import HelpOutlineIcon from 'gridicons/dist/help-outline';

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
	const depositRows = recentDeposits.deposits.map( ( deposit ) => {
		let bannerNotice = null;

		if ( 'pending' === deposit.status || 'in_transit' === deposit.status ) {
			bannerNotice = (
				<BannerNotice
					status="info"
					icon={ <HelpOutlineIcon /> }
					children={ strings.notices.businessDayDelay }
					isDismissible={ false }
				/>
			);
		}

		return (
			<>
				<DepositTableRow deposit={ deposit } />
				{ bannerNotice }
			</>
		);
	} );

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
