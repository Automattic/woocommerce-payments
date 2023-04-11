/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';

/**
 * Internal dependencies.
 */
import { useSelectedCurrencyOverview } from 'wcpay/overview/hooks';
import strings from './strings';
import NextDepositDetails from './next-deposit';
import RecentDepositsList from './recent-deposits-list';
import DepositsOverviewFooter from './footer';
import DepositOverviewSectionHeading from './section-heading';
import DepositSchedule from './deposit-schedule';
import SuspendedDepositNotice from './suspended-deposit-notice';
import BannerNotice from 'wcpay/components/banner-notice';
import './style.scss';

const DepositsOverview = (): JSX.Element => {
	const { account, overview, isLoading } = useSelectedCurrencyOverview();
	const completedWaitingPeriod =
		wcpaySettings.accountStatus.deposits?.completed_waiting_period;

	const userHasNotFinishedNewAccountWaitingPeriodNotice = createInterpolateElement(
		/* translators: <link> - link to WCPay deposit schedule docs. */
		__(
			'Your first deposit is held for seven business days. <link>Why?</link>',
			'woocommerce-payments'
		),
		{
			link: (
				// eslint-disable-next-line jsx-a11y/anchor-has-content
				<a
					target="_blank"
					rel="noopener noreferrer"
					href="https://woocommerce.com/document/woocommerce-payments/deposits/deposit-schedule/#section-1"
				/>
			),
		}
	);

	return (
		<Card className="wcpay-deposits-overview">
			<CardHeader>{ strings.heading }</CardHeader>

			{ /* Only show the next deposit section if the page is loading or if deposits are not blocked. */ }
			{ ( isLoading || ! account?.deposits_blocked ) && (
				<>
					<DepositOverviewSectionHeading
						title={ strings.nextDeposit.title }
						text={ strings.nextDeposit.description }
						isLoading={ isLoading }
					/>
					<NextDepositDetails
						isLoading={ isLoading }
						overview={ overview }
					/>
					{ ! completedWaitingPeriod && (
						<BannerNotice
							status="warning"
							icon={ <NoticeOutlineIcon /> }
							className="new-account-waiting-period-notice"
							children={
								userHasNotFinishedNewAccountWaitingPeriodNotice
							}
							isDismissible={ false }
						/>
					) }
				</>
			) }

			{ ! isLoading &&
				!! account &&
				( account.deposits_blocked ? (
					<DepositOverviewSectionHeading
						title={ strings.depositHistoryHeading }
						children={ <SuspendedDepositNotice /> }
					/>
				) : (
					<DepositOverviewSectionHeading
						title={ strings.depositHistoryHeading }
						text={
							<DepositSchedule { ...account.deposits_schedule } />
						}
					/>
				) ) }

			{ overview?.currency && (
				<RecentDepositsList currency={ overview.currency } />
			) }

			<DepositsOverviewFooter />
		</Card>
	);
};

export default DepositsOverview;
