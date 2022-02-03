/**
 * External dependencies
 */
import { Card, CardBody } from '@wordpress/components';
import Page from 'components/page';
/**
 * Internal dependencies.
 */
import PreviewReceipt from './preview-receipt';
import ErrorBoundary from 'components/error-boundary';

export const PreviewPrintReceipt = () => {
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
