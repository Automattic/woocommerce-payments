/**
 * External dependencies
 */
import React from 'react';
import { Card, CardBody } from '@wordpress/components';
import Page from 'components/page';
/**
 * Internal dependencies.
 */
import PreviewReceipt from './receipt';
import ErrorBoundary from 'components/error-boundary';

export const PreviewPrintReceipt = (): JSX.Element => {
	return (
		<Page isNarrow className="wcpay-card-readers-preview-receipt-page">
			<Card>
				<CardBody size="small">
					<ErrorBoundary>
						<PreviewReceipt />
					</ErrorBoundary>
				</CardBody>
			</Card>
		</Page>
	);
};

export default PreviewPrintReceipt;
