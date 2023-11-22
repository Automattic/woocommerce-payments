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
import { notice } from './strings';
import { getPaymentSettingsUrl } from 'wcpay/utils';

const DepositsPage: React.FC = () => {
	return (
		<Page>
			<TestModeNotice
				actions={ [
					{
						label: notice.action,
						url: getPaymentSettingsUrl(),
					},
				] }
			>
				{ notice.content }
			</TestModeNotice>
			<DepositsList />
		</Page>
	);
};

export default DepositsPage;
