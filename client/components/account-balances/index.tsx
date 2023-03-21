/**
 * External dependencies
 */
import React from 'react';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import AccountBalancesHeader from './header';
import './style.scss';
import AccountBalancesTabPanel from './balances-tab-panel';

/**
 * Renders an overview of the account's balances.
 *
 * @return {JSX.Element} Rendered element with the account balances card.
 */
const AccountBalances: React.FC = () => {
	return (
		<Card className="wcpay-account-balances">
			<AccountBalancesHeader />

			<AccountBalancesTabPanel />
		</Card>
	);
};

export default AccountBalances;
