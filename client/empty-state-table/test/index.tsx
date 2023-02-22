/** @format */

/**
 * External dependencies
 */
import * as React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import EmptyStateTable from 'wcpay/empty-state-table';
import {
	EmptyStateList,
	EmptyStateTableHeaders,
} from 'wcpay/empty-state-table/list';
import TransactionsBanner from 'assets/images/transactions-banner.svg';
import DepositsBanner from 'assets/images/deposits-banner.svg';

declare const global: {
	wcpaySettings: {
		isJetpackConnected: boolean;
		connectUrl: string;
	};
};

describe( 'Empty state table', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		global.wcpaySettings = {
			isJetpackConnected: false,
			connectUrl: 'https://connect',
		};
	} );

	test( 'renders correctly for transaction', () => {
		const { container } = render(
			<EmptyStateTable
				headers={ EmptyStateTableHeaders }
				title="Transactions"
				content={ <EmptyStateList listBanner={ TransactionsBanner } /> }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders correctly for deposits', () => {
		const { container } = render(
			<EmptyStateTable
				headers={ EmptyStateTableHeaders }
				title="Deposit history"
				content={ <EmptyStateList listBanner={ DepositsBanner } /> }
			/>
		);
		expect( container ).toMatchSnapshot();
	} );
} );
