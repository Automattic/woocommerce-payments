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
import {
	EmptyStateList,
	EmptyStateTableHeaders,
} from '../emtpy-state-table/list';
import EmptyStateTable from 'emtpy-state-table';
import ListBanner from '../emtpy-state-table/deposits-banner.svg';
import { Experiment } from '@woocommerce/explat';

const DepositsPage = () => {
	return (
		<Page>
			<Experiment
				name="wcpay_empty_state_preview_mode"
				treatmentExperience={
					<>
						<EmptyStateTable
							headers={ EmptyStateTableHeaders }
							title="Deposit history"
							content={
								<EmptyStateList listBanner={ ListBanner } />
							}
						/>
					</>
				}
				defaultExperience={
					<>
						<TestModeNotice topic={ topics.deposits } />
						<DepositsOverview />
						<DepositsList />
					</>
				}
			/>
		</Page>
	);
};

export default DepositsPage;
