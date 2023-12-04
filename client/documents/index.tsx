/**
 * External dependencies
 */
import React from 'react';

/**
 * Internal dependencies
 */
import Page from 'components/page';
import DocumentsList from './list';
import { TestModeNotice } from 'components/test-mode-notice';

export const DocumentsPage = (): JSX.Element => {
	return (
		<Page>
			<TestModeNotice currentPage="documents" />
			<DocumentsList />
		</Page>
	);
};

export default DocumentsPage;
