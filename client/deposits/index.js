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
import {
	EmptyStateList,
	EmptyStateTableHeaders,
} from '../empty-state-table/list';
import EmptyStateTable from 'empty-state-table';
import ListBanner from '../empty-state-table/deposits-banner.svg';
import { Experiment } from '@woocommerce/explat';

const DepositsPage = () => {
	return (
		<Page>
			<Experiment
				name="wcpay_empty_state_preview_mode_v2"
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
						<DepositsList />
					</>
				}
			/>
		</Page>
	);
};

export default DepositsPage;
