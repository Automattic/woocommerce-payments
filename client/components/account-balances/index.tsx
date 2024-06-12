/**
 * External dependencies
 */
import * as React from 'react';
import { Flex } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useAllDepositsOverviews } from 'wcpay/data';
import { useSelectedCurrency } from 'wcpay/overview/hooks';
import BalanceBlock from './balance-block';
import {
	TotalBalanceTooltip,
	AvailableBalanceTooltip,
} from './balance-tooltip';
import { fundLabelStrings } from './strings';
import InstantDepositButton from 'deposits/instant-deposits';
import type * as AccountOverview from 'wcpay/types/account-overview';
import './style.scss';

/**
 * Renders account balances for the selected currency.
 */
const AccountBalances: React.FC = () => {
	const { overviews, isLoading } = useAllDepositsOverviews();
	const { selectedCurrency } = useSelectedCurrency();

	if ( ! isLoading && overviews.currencies.length === 0 ) {
		return null;
	}

	if ( isLoading ) {
		// While the data is loading, we show a loading state for the balances.
		const loadingData = {
			name: 'loading',
			currencyCode: wcpaySettings.accountDefaultCurrency,
			availableFunds: 0,
			pendingFunds: 0,
			delayDays: 0,
		};

		return (
			<Flex gap={ 0 } className="wcpay-account-balances__balances">
				<BalanceBlock
					id={ `wcpay-account-balances-${ loadingData.currencyCode }-total` }
					title={ fundLabelStrings.total }
					amount={ 0 }
					currencyCode={ loadingData.currencyCode }
					isLoading
				/>
				<BalanceBlock
					id={ `wcpay-account-balances-${ loadingData.currencyCode }-available` }
					title={ fundLabelStrings.available }
					amount={ 0 }
					currencyCode={ loadingData.currencyCode }
					isLoading
				/>
			</Flex>
		);
	}

	const { currencies, account } = overviews;

	const depositCurrencyOverviews = currencies.map(
		( overview: AccountOverview.Overview ) => ( {
			name: overview.currency,
			currencyCode: overview.currency,
			availableFunds: overview.available?.amount ?? 0,
			pendingFunds: overview.pending?.amount ?? 0,
			delayDays: account?.deposits_schedule.delay_days ?? 0,
			instantBalance: overview.instant,
		} )
	);

	const selectedOverview =
		depositCurrencyOverviews.find(
			( overview ) => overview.name === selectedCurrency
		) || depositCurrencyOverviews[ 0 ];

	const totalBalance =
		selectedOverview.availableFunds + selectedOverview.pendingFunds;

	return (
		<>
			<Flex gap={ 0 } className="wcpay-account-balances__balances">
				<BalanceBlock
					id={ `wcpay-account-balances-${ selectedOverview.currencyCode }-total` }
					title={ fundLabelStrings.total }
					amount={ totalBalance }
					currencyCode={ selectedOverview.currencyCode }
					tooltip={ <TotalBalanceTooltip balance={ totalBalance } /> }
				/>
				<BalanceBlock
					id={ `wcpay-account-balances-${ selectedOverview.currencyCode }-available` }
					title={ fundLabelStrings.available }
					amount={ selectedOverview.availableFunds }
					currencyCode={ selectedOverview.currencyCode }
					tooltip={
						<AvailableBalanceTooltip
							balance={ selectedOverview.availableFunds }
						/>
					}
				/>
			</Flex>
			{ selectedOverview.instantBalance &&
				selectedOverview.instantBalance.amount > 0 && (
					<Flex
						gap={ 0 }
						className="wcpay-account-balances__instant-deposit"
					>
						<InstantDepositButton
							instantBalance={ selectedOverview.instantBalance }
						/>
					</Flex>
				) }
		</>
	);
};

export default AccountBalances;
