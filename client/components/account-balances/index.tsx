/**
 * External dependencies
 */
import React from 'react';
import { Card, CardHeader, Flex, TabPanel } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { AccountOverview } from 'wcpay/types/account-overview';
import './style.scss';

/**
 * Renders an overview of the account's balances.
 *
 * @return {JSX.Element} Rendered element with the account balances card.
 */
const AccountBalances: React.FC = () => {
	// Currency is hardcoded for now, but will be populated via useAllDepositsOverviews().
	const currency: AccountOverview.Overview[ 'currency' ] = 'JPY';
	const tabs: TabPanel.Tab[] = [
		{
			name: currency,
			title: `${ currency.toUpperCase() } Balance`,
		},
	];

	return (
		<Card className="wcpay-account-balances">
			<CardHeader size="small">Good afternoon</CardHeader>

			<TabPanel tabs={ tabs }>
				{ ( tab ) => (
					<Flex
						gap={ 0 }
						className="wcpay-account-balances__balances"
					>
						<div className="wcpay-account-balances__balances__item">
							Available funds
						</div>
						<div className="wcpay-account-balances__balances__item">
							Pending funds
						</div>
						<div className="wcpay-account-balances__balances__item">
							Reserved funds
						</div>
					</Flex>
				) }
			</TabPanel>
		</Card>
	);
};

export default AccountBalances;
