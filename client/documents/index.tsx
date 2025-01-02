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
import DateFormatNotice from 'wcpay/components/date-format-notice';
export const DocumentsPage = (): JSX.Element => {
	return (
		<Page>
			<DateFormatNotice />
			<TestModeNotice currentPage="documents" />
			<DocumentsList />
		</Page>
	);
};

export default DocumentsPage;
