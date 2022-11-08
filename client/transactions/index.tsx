/** @format **/

/**
 * Internal dependencies
 */
import React from 'react';
import Page from 'components/page';
import TransactionsPage from './transactions-tabs';
import WCPaySettingsContext from '../settings/wcpay-settings-context';

declare const window: any;

const Transactions = () => {
	return (
		<Page>
			<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
				<TransactionsPage />
			</WCPaySettingsContext.Provider>
		</Page>
	);
};

export default Transactions;
