/**
 * External dependencies
 */
import React from 'react';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import AccountBalancesTabPanel from './balances-tab-panel';
import AccountOverviewHeader from 'components/account-overview-header';

interface Props {
	/**
	 * The number of disputes that need a response.
	 */
	numDisputesNeedingResponse?: number;
}

/**
 * Renders an overview of the account's balances.
 *
 * @param {Object} props Component props.
 * @return {JSX.Element} Rendered element with the account balances card.
 */
const AccountBalances: React.FC< Props > = ( {
	numDisputesNeedingResponse = 0,
} ) => {
	if ( numDisputesNeedingResponse > 0 ) {
		// If there are disputes that need a response, we want to show the
		// welcome header and the notice at the top of the page, in a separate card
		// to the balances tab panel.
		return (
			<>
				<Card>
					<AccountOverviewHeader />
				</Card>
				<Card className="wcpay-account-balances">
					<AccountBalancesTabPanel />
				</Card>
			</>
		);
	}

	return (
		<Card className="wcpay-account-balances">
			<AccountOverviewHeader />

			<AccountBalancesTabPanel />
		</Card>
	);
};

export default AccountBalances;
