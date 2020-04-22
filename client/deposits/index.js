/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */
import Page from 'components/page';
import DepositsOverview from './overview';
import DepositsList from './list';

const DepositsPage = () => {
	return (
		<Page>
			<DepositsOverview />
			<DepositsList />
		</Page>
	);
};

export default DepositsPage;
