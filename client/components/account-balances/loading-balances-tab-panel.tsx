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

/**
 * Renders an account balances panel in a loading state.
 * The panel includes tab navigation for each deposit currency and balances for each fund type.
 *
 * @return {React.FunctionComponent} Rendered balances panel with tab navigation for each currency with Loadable components.
 */
const LoadingBalancesTabPanel: React.FC = () => {
	const loadingTabs: TabPanel.Tab[] = [
		{
			name: 'loading',
			title: sprintf(
				// string format: {currency} balance
				currencyBalanceString,
				// While the screen is loading, pull the default currency from the settings object.
				wcpaySettings.accountDefaultCurrency.toUpperCase()
			),
		},
	];

	const loadingFund = (
		<Loadable
			isLoading={ true }
			display="inline"
			placeholder="loading amount"
		/>
	);

	return (
		<TabPanel tabs={ loadingTabs }>
			{ ( tab ) => (
				<Flex gap={ 0 } className="wcpay-account-balances__balances">
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.available }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ loadingFund }
						</p>
					</div>
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.pending }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ loadingFund }
						</p>
					</div>
					<div className="wcpay-account-balances__balances__item">
						<p className="wcpay-account-balances__balances__item__title">
							{ fundLabelStrings.reserve }
						</p>
						<p className="wcpay-account-balances__balances__item__amount">
							{ loadingFund }
						</p>
					</div>
				</Flex>
			) }
		</TabPanel>
	);
};

export default LoadingBalancesTabPanel;
