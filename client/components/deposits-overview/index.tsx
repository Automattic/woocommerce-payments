/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardHeader, ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';

/**
 * Internal dependencies.
 */
import { useAllDepositsOverviews } from 'wcpay/data';
import strings from './strings';
import NextDepositDetails from './next-deposit';
import BannerNotice from 'wcpay/components/banner-notice';
import DepositsOverviewFooter from './footer';
import './style.scss';

const DepositsOverview = (): JSX.Element => {
	const {
		overviews,
		isLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;

	const completedWaitingPeriod = (
		wcpaySettings.accountStatus.deposits || {}
	).completed_waiting_period;

	const { currencies } = overviews;

	const overview = currencies[ 0 ]; // TODO: To handle multiple currencies we'll need to fetch the currently selected currency.

	const userHasNotFinishedNewAccountWaitingPeriodNotice = createInterpolateElement(
		/* translators: <link> - link to WCPay deposit schedule docs. */
		__(
			'Your first deposit is held for seven business days. <link>Why?</link>',
			'woocommerce-payments'
		),
		{
			link: (
				<ExternalLink href="https://woocommerce.com/document/woocommerce-payments/deposits/deposit-schedule/#section-1" />
			),
		}
	);

	return (
		<Card className="wcpay-deposits-overview">
			<CardHeader>{ strings.heading }</CardHeader>
			<NextDepositDetails isLoading={ isLoading } overview={ overview } />

			{ ! completedWaitingPeriod && (
				<BannerNotice
					status="warning"
					icon={ <NoticeOutlineIcon /> }
					className="new-account-waiting-period-notice"
					children={ userHasNotFinishedNewAccountWaitingPeriodNotice }
					isDismissible={ false }
				/>
			) }

			<p>Deposits History Section Goes here</p>

			<p>Deposits Card Footer/Action Goes here</p>

			<DepositsOverviewFooter />
		</Card>
	);
};

export default DepositsOverview;
