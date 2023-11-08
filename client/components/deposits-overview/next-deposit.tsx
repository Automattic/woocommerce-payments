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
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Loadable from 'components/loadable';
import { getNextDeposit } from './utils';
import DepositStatusChip from 'components/deposit-status-chip';
import { getDepositDate } from 'deposits/utils';
import { useAllDepositsOverviews, useDepositIncludesLoan } from 'wcpay/data';
import { useSelectedCurrency } from 'wcpay/overview/hooks';
import type * as AccountOverview from 'wcpay/types/account-overview';
import {
	DepositIncludesLoanPayoutNotice,
	NegativeBalanceDepositsPausedNotice,
	NewAccountWaitingPeriodNotice,
} from './deposit-notices';

type NextDepositProps = {
	isLoading: boolean;
	overview?: AccountOverview.Overview;
};

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
								<DepositStatusChip
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
