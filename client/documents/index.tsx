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
import { MaybeShowMerchantFeedbackPrompt } from 'wcpay/merchant-feedback-prompt';
import ErrorBoundary from 'components/error-boundary';
import SpotlightPromotion from 'promotions/spotlight';
export const DocumentsPage = (): JSX.Element => {
	return (
		<Page>
			<MaybeShowMerchantFeedbackPrompt />
			<TestModeNotice currentPage="documents" />
			<DocumentsList />
			<ErrorBoundary>
				<SpotlightPromotion />
			</ErrorBoundary>
		</Page>
	);
};

export default DocumentsPage;
