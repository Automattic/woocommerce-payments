/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import DepositsOverview from './overview';
import DepositsList from './list';
import DepositsFilters from '../deposits/filters';

const DepositsPage = () => {
	return (
		<Page>
			<TestModeNotice topic={ topics.deposits } />
			<DepositsFilters />
			<DepositsList />

			<DepositsOverview />
		</Page>
	);
};

export default DepositsPage;
