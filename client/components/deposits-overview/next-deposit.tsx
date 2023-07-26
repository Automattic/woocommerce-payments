/**
 * External dependencies
 */
import * as React from 'react';
import {
	CardBody,
	CardDivider,
	Flex,
	FlexItem,
	Icon,
} from '@wordpress/components';
import { calendar } from '@wordpress/icons';
import InfoOutlineIcon from 'gridicons/dist/info-outline';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';
import interpolateComponents from '@automattic/interpolate-components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Loadable from 'components/loadable';
import { getNextDeposit } from './utils';
import DepositStatusPill from 'components/deposit-status-pill';
import { getDepositDate } from 'deposits/utils';
import { useAllDepositsOverviews, useDepositIncludesLoan } from 'wcpay/data';
import BannerNotice from 'wcpay/components/banner-notice';
import { useSelectedCurrency } from 'wcpay/overview/hooks';
import * as AccountOverview from 'wcpay/types/account-overview';

type NextDepositProps = {
	isLoading: boolean;
	overview?: AccountOverview.Overview;
};

const DepositIncludesLoanPayoutNotice = () => (
	<BannerNotice
		status="warning"
		icon={ <InfoOutlineIcon /> }
		isDismissible={ false }
	>
		{ interpolateComponents( {
			mixedString: __(
				'This deposit will include funds from your WooCommerce Capital loan. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
				'woocommerce-payments'
			),
			components: {
				learnMoreLink: (
					// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						href={
							'https://woocommerce.com/document/woocommerce-payments/stripe-capital/overview'
						}
						target="_blank"
						rel="noreferrer"
					/>
				),
			},
		} ) }
	</BannerNotice>
);

const NewAccountWaitingPeriodNotice = () => (
	<BannerNotice
		status="warning"
		icon={ <NoticeOutlineIcon /> }
		className="new-account-waiting-period-notice"
		isDismissible={ false }
	>
		{ interpolateComponents( {
			mixedString: __(
				'Your first deposit is held for seven business days. {{whyLink}}Why?{{/whyLink}}',
				'woocommerce-payments'
			),
			components: {
				whyLink: (
					// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						target="_blank"
						rel="noopener noreferrer"
						href="https://woocommerce.com/document/woocommerce-payments/deposits/deposit-schedule/#section-1"
					/>
				),
			},
		} ) }
	</BannerNotice>
);

const NegativeBalanceDepositsPausedNotice = () => (
	<BannerNotice
		status="warning"
		icon={ <NoticeOutlineIcon /> }
		className="negative-balance-deposits-paused-notice"
		isDismissible={ false }
	>
		{ interpolateComponents( {
			mixedString: sprintf(
				/* translators: %s: WooPayments */
				__(
					'Deposits may be interrupted while your %s balance remains negative. {{whyLink}}Why?{{/whyLink}}',
					'woocommerce-payments'
				),
				'WooPayments'
			),
			components: {
				whyLink: (
					// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						target="_blank"
						rel="noopener noreferrer"
						href="https://woocommerce.com/document/woocommerce-payments/fees-and-debits/account-showing-negative-balance/"
					/>
				),
			},
		} ) }
	</BannerNotice>
);

/**
 * Renders the Next Deposit details component.
 *
 * This component included the next deposit heading, table and notice.
 *
 * @param {NextDepositProps} props Next Deposit details props.
 * @return {JSX.Element} Rendered element with Next Deposit details.
 */
const NextDepositDetails: React.FC< NextDepositProps > = ( {
	isLoading,
	overview,
} ): JSX.Element => {
	const tableClass = 'wcpay-deposits-overview__table';
	const nextDeposit = getNextDeposit( overview );
	const nextDepositDate = getDepositDate(
		nextDeposit.date > 0 ? nextDeposit : null
	);

	const { includesFinancingPayout } = useDepositIncludesLoan(
		nextDeposit.id
	);
	const completedWaitingPeriod =
		wcpaySettings.accountStatus.deposits?.completed_waiting_period;

	const {
		overviews,
	} = useAllDepositsOverviews() as AccountOverview.OverviewsResponse;
	const { selectedCurrency } = useSelectedCurrency();
	const displayedCurrency =
		selectedCurrency ?? wcpaySettings.accountDefaultCurrency;

	const availableBalance = overviews?.currencies.find(
		( currencyOverview ) => displayedCurrency === currencyOverview.currency
	)?.available;

	const negativeBalanceDepositsPaused =
		availableBalance && availableBalance.amount < 0;

	return (
		<>
			{ /* Next Deposit Table */ }
			<CardBody className={ `${ tableClass }__container` }>
				<Flex className={ `${ tableClass }__row__header` }>
					<FlexItem className={ `${ tableClass }__cell` }>
						<Loadable
							isLoading={ isLoading }
							value={ __(
								'Estimated dispatch date',
								'woocommerce-payments'
							) }
						/>
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						<Loadable
							isLoading={ isLoading }
							value={ __( 'Status', 'woocommerce-payments' ) }
						/>
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						<Loadable
							isLoading={ isLoading }
							value={ __( 'Amount', 'woocommerce-payments' ) }
						/>
					</FlexItem>
				</Flex>
			</CardBody>
			<CardDivider />
			<CardBody className={ `${ tableClass }__container` }>
				<Flex className={ `${ tableClass }__row` }>
					<FlexItem className={ `${ tableClass }__cell` }>
						{ ! isLoading && (
							<Icon icon={ calendar } size={ 17 } />
						) }
						<Loadable
							isLoading={ isLoading }
							placeholder="MMMM DD, YYYY"
							value={ nextDepositDate }
						/>
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						<Loadable
							isLoading={ isLoading }
							placeholder="Estimated"
							children={
								<DepositStatusPill
									status={ nextDeposit.status }
								/>
							}
						/>
					</FlexItem>
					<FlexItem className={ `${ tableClass }__cell` }>
						<Loadable
							isLoading={ isLoading }
							placeholder="$00,000.00"
							value={ nextDeposit.amount }
						/>
					</FlexItem>
				</Flex>
			</CardBody>
			{ /* Notices */ }
			{ ! isLoading && (
				<CardBody
					className={ 'wcpay-deposits-overview__notices__container' }
				>
					{ includesFinancingPayout && (
						<DepositIncludesLoanPayoutNotice />
					) }
					{ ! completedWaitingPeriod && (
						<NewAccountWaitingPeriodNotice />
					) }
					{ negativeBalanceDepositsPaused && (
						<NegativeBalanceDepositsPausedNotice />
					) }
				</CardBody>
			) }
		</>
	);
};

export default NextDepositDetails;
