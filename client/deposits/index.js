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
import ListBanner from 'assets/images/deposits-banner.svg?asset';
import { Experiment } from '@woocommerce/explat';

const DepositsPage = () => {
	const defaultExperience = (
		<>
			<TestModeNotice topic={ topics.deposits } />
			<DepositsList />
		</>
	);

	const treatmentExperience = wcpaySettings.accountStatus.status ? (
		defaultExperience
	) : (
		<EmptyStateTable
			headers={ EmptyStateTableHeaders }
			title="Deposit history"
			content={
				<EmptyStateList
					listBanner={ ( props ) => (
						<img
							src={ ListBanner }
							alt="deposits banner"
							{ ...props }
						/>
					) }
				/>
			}
		/>
	);

	return (
		<Page>
			<Experiment
				name="wcpay_empty_state_preview_mode_v5"
				treatmentExperience={ treatmentExperience }
				defaultExperience={ defaultExperience }
			/>
		</Page>
	);
};

export default DepositsPage;
