/**
 * External dependencies
 */
import * as React from 'react';
import { Card, CardHeader } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';

/**
 * Internal dependencies.
 */
import { useAllDepositsOverviews } from 'wcpay/data';
import strings from './strings';
import NextDepositDetails from './next-deposit';
import DepositsOverviewFooter from './footer';
import DepositOverviewSectionHeading from './section-heading';
import { getDepositScheduleDescription, areDepositsBlocked } from './utils';
import BannerNotice from '../banner-notice';

const DepositsOverview = (): JSX.Element => {
	const {
		overviews,
		isLoading,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;

	const { currencies, account } = overviews;

	const overview = currencies[ 0 ]; // TODO: To handle multiple currencies we'll need to fetch the currently selected currency.

	return (
		<Card>
			<CardHeader>{ strings.heading }</CardHeader>

			{ ( isLoading || ! areDepositsBlocked( account ) ) && (
				<>
					<DepositOverviewSectionHeading
						title={ strings.nextDeposit.title }
						description={ strings.nextDeposit.description }
						isLoading={ isLoading }
					/>
					<NextDepositDetails
						isLoading={ isLoading }
						overview={ overview }
					/>
				</>
			) }

			{ ! isLoading && (
				<DepositOverviewSectionHeading
					title={ strings.depositHistory.title }
					description={
						// If deposits are blocked, the description should include a banner notice.
						areDepositsBlocked( account ) ? (
							<BannerNotice
								className="wcpay-deposits-overview__notice"
								status="warning"
								icon={ <NoticeOutlineIcon /> }
								isDismissible={ false }
							>
								{ interpolateComponents( {
									mixedString:
										strings.depositHistory.descriptions
											.suspended,
									components: {
										strong: <strong />,
										suspendLink: (
											<Link
												href={
													'https://woocommerce.com/document/payments/faq/deposits-suspended/'
												}
											/>
										),
									},
								} ) }
							</BannerNotice>
						) : (
							getDepositScheduleDescription( account )
						)
					}
				/>
			) }

			<p>Deposit history table will go here</p>

			<DepositsOverviewFooter />
		</Card>
	);
};

export default DepositsOverview;
