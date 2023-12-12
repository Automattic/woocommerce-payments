/** @format **/

/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice } from 'components/test-mode-notice';
import DepositsList from './list';

const DepositsPage: React.FC = () => {
	return (
		<Page>
			<TestModeNotice currentPage="deposits" />
			<DepositsList />
		</Page>
	);
};

export default DepositsPage;
