/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import AccountStatus from 'components/account-status';

const OverviewPage = () => {
	return (
		<Page>
			<TestModeNotice topic={ topics.overview } />
			<AccountStatus
				accountStatus={ wcpaySettings.accountStatus }
				accountFees={ wcpaySettings.accountFees }
			/>
		</Page>
	);
};

export default OverviewPage;
