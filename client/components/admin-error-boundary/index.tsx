/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import InlineNotice from '../inline-notice';
import ErrorBoundary from '../error-boundary';
import React from 'react';

const AdminErrorFallback = ( { error }: { error: any } ) => {
	return (
		<InlineNotice icon status="error" isDismissible={ false }>
			{ __(
				'There was an error rendering this view. Please contact support for assistance if the problem persists.',
				'woocommerce-payments'
			) }
			<br />
			{ error.toString() }
		</InlineNotice>
	);
};

const AdminErrorBoundary: React.FC = ( { children } ) => {
	return (
		<ErrorBoundary fallbackRender={ AdminErrorFallback }>
			{ children }
		</ErrorBoundary>
	);
};

export default AdminErrorBoundary;
