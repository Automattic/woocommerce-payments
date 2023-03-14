/**
 * External dependencies
 */
import * as React from 'react';
import Loadable from 'components/loadable';
import { Flex, TabPanel } from '@wordpress/components';
import { sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { currencyBalanceString, fundLabelStrings } from './strings';

const LoadingFundsTabTable: React.FC = () => {
	const loadingTabs: TabPanel.Tab[] = [
		{
			name: 'loading',
			title: sprintf(
				currencyBalanceString,
				wcpaySettings.accountDefaultCurrency.toUpperCase()
			),
			loading_funds: (
				<Loadable
					isLoading={ true }
					display="inline"
					placeholder="$000.00"
				/>
			),
		},
	];

	return (
		<TabPanel tabs={ loadingTabs }>
			{ ( tab ) => (
				<Flex gap={ 0 } className="wcpay-account-balances__balances">
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.available }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ tab.loading_funds }
						</p>
					</div>
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.pending }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ tab.loading_funds }
						</p>
					</div>
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.reserve }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ tab.loading_funds }
						</p>
					</div>
				</Flex>
			) }
		</TabPanel>
	);
};

export default LoadingFundsTabTable;
