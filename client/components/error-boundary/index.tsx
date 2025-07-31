/**
 * External dependencies
 */
import React, { Component } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import InlineNotice from 'wcpay/components/inline-notice';

interface ErrorBoundaryProps {
	children: any;
	onError?: ( error: Error, errorInfo: any ) => void;
}

interface ErrorBoundaryState {
	error: Error | null;
}

export default class ErrorBoundary extends Component<
	ErrorBoundaryProps,
	ErrorBoundaryState
> {
	constructor( props: ErrorBoundaryProps ) {
		super( props );

		this.state = {
			error: null,
		};
	}

	static getDerivedStateFromError( error: Error ): { error: Error } {
		return { error };
	}

	componentDidCatch( error: Error, info: any ): void {
		if ( this.props.onError ) {
			this.props.onError( error, info );
		}
	}

	render(): any {
		if ( ! this.state.error ) {
			return this.props.children;
		}

		return (
			<InlineNotice icon status="error" isDismissible={ false }>
				{ __(
					'There was an error rendering this view. Please contact support for assistance if the problem persists.',
					'woocommerce-payments'
				) }
				<br />
				{ this.state.error.toString() }
			</InlineNotice>
		);
	}
}
