/**
 * External dependencies
 */
import React from 'react';
import { Card, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import AccountBalancesHeader from './header';
import './style.scss';
import AccountBalancesTabPanel from './balances-tab-panel';

interface Props {
	/**
	 * The number of the disputes that need a response.
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
		// When there are disputes that need a response, we want to show the
		// welcome header and the notice at the top of the page, separate from
		// the tab panel.
		return (
			<>
				<Card>
					<AccountBalancesHeader />
					<Notice status="error" isDismissible={ false }>
						{ numDisputesNeedingResponse } disputes need a response
					</Notice>
				</Card>

				<Card className="wcpay-account-balances">
					<AccountBalancesTabPanel />
				</Card>
			</>
		);
	}

	return (
		<Card className="wcpay-account-balances">
			<AccountBalancesHeader />

			<AccountBalancesTabPanel />
		</Card>
	);
};

export default AccountBalances;
