/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import Page from 'components/page';
import DocumentsList from './list';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import {
	EmptyStateList,
	EmptyStateTableHeaders,
} from '../empty-state-table/list';
import EmptyStateTable from 'empty-state-table';
// TODO: Need a documents banner created.
import ListBanner from '../empty-state-table/transactions-banner.svg';
import { Experiment } from '@woocommerce/explat';

export const DocumentsPage = (): JSX.Element => {
	const defaultExperience = (
		<>
			<TestModeNotice topic={ topics.documents } />
			<DocumentsList />
		</>
	);

	const treatmentExperience = wcpaySettings.accountStatus.status ? (
		defaultExperience
	) : (
		<EmptyStateTable
			headers={ EmptyStateTableHeaders }
			title="Documents"
			content={ <EmptyStateList listBanner={ ListBanner } /> }
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

export default DocumentsPage;
