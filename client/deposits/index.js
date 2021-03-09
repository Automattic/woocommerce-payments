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
import { useDepositsPage } from 'data';

const DepositsPage = () => {
	const { needsReload } = useDepositsPage();
	console.log( 'needsReload' );
	console.log( needsReload );
	return (
		<>
			{ ! needsReload && (
				<Page>
					<TestModeNotice topic={ topics.deposits } />
					<DepositsOverview />
					<DepositsList />
				</Page>
			) }
		</>
	);
};

export default DepositsPage;
