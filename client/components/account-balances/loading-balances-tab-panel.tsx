/**
 * External dependencies
 */
import * as React from 'react';
import { Flex, TabPanel } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { fundLabelStrings } from './strings';
import BalanceBlock from './balance-block';
import { getCurrencyTabTitle } from './utils';

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
			title: getCurrencyTabTitle( wcpaySettings.accountDefaultCurrency ),
		},
	];

	return (
		<TabPanel tabs={ loadingTabs }>
			{ ( tab ) => (
				<Flex gap={ 0 } className="wcpay-account-balances__balances">
					<BalanceBlock
						title={ fundLabelStrings.available }
						isLoading={ true }
						currencyCode={ tab.currency_code }
					/>
					<BalanceBlock
						title={ fundLabelStrings.pending }
						isLoading={ true }
						currencyCode={ tab.currency_code }
					/>
					<BalanceBlock
						title={ fundLabelStrings.reserve }
						isLoading={ true }
						currencyCode={ tab.currency_code }
					/>
				</Flex>
			) }
		</TabPanel>
	);
};

export default LoadingBalancesTabPanel;
