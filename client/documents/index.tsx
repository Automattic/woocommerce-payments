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
import { notice } from './strings';
import { getPaymentSettingsUrl } from 'wcpay/utils';

export const DocumentsPage = (): JSX.Element => {
	return (
		<Page>
			<TestModeNotice
				actions={ [
					{
						label: notice.action,
						url: getPaymentSettingsUrl(),
					},
				] }
			>
				{ notice.content }
			</TestModeNotice>
			<DocumentsList />
		</Page>
	);
};

export default DocumentsPage;
