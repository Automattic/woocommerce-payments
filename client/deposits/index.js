/** @format **/

/**
 * External dependencies
 */

/**
 * Internal dependencies.
 */
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import DepositsList from './list';

const DepositsPage = () => {
	return (
		<Page>
			<TestModeNotice topic={ topics.deposits } />
			<DepositsList />
		</Page>
	);
};

export default DepositsPage;
