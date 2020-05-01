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
import includeStripeJS from '../hooks/include-stripe-js';

const DepositsPage = () => {
	includeStripeJS();

	return (
		<Page>
			<DepositsOverview />
			<DepositsList />
		</Page>
	);
};

export default DepositsPage;
