/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestNotice, topics } from 'components/test-mode-notice';
import DepositsOverview from './overview';
import DepositsList from './list';

const DepositsPage = () => {
	return (
		<Page>
			<TestNotice topic={ topics.deposits } />
			<DepositsOverview />
			<DepositsList />
		</Page>
	);
};

export default DepositsPage;
