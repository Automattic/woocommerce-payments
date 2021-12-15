/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import InlineNotice from '../inline-notice';

class ErrorBoundary extends Component {
	constructor() {
		super( ...arguments );

		this.state = {
			error: null,
		};
	}

	static getDerivedStateFromError( error ) {
		return { error };
	}

	componentDidCatch( error, info ) {
		if ( this.props.onError ) {
			this.props.onError( error, info );
		}
	}

	render() {
		if ( ! this.state.error ) {
			return this.props.children;
		}

		return (
			<InlineNotice status="error" isDismissible={ false }>
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

export default ErrorBoundary;
