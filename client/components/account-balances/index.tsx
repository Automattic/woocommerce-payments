/**
 * External dependencies
 */
import React from 'react';
import { Card, CardHeader } from '@wordpress/components';
/**
 * Internal dependencies
 */
import './style.scss';
import AccountBalancesTabTable from './funds-tab-panel';

/**
 * Renders an overview of the account's balances.
 *
 * @return {React.FunctionComponent} Rendered element with the account balances card.
 */
const AccountBalances: React.FC = () => {
	return (
		<Card className="wcpay-account-balances">
			<CardHeader size="small">Good afternoon</CardHeader>

			<AccountBalancesTabTable />
		</Card>
	);
};

export default AccountBalances;
