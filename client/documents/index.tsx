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
import MerchantFeedbackPrompt from 'wcpay/merchant-feedback-prompt';
export const DocumentsPage = (): JSX.Element => {
	return (
		<Page>
			<MerchantFeedbackPrompt />
			<TestModeNotice currentPage="documents" />
			<DocumentsList />
		</Page>
	);
};

export default DocumentsPage;
