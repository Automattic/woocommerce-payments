/**
 * External dependencies
 */
import { Component } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const DevFallback = ( { error } ) => {
	if ( process.env.MODE === 'production' ) {
		return null;
	}

	return (
		<div
			style={ {
				padding: '5px 10px',
				background: 'papayawhip',
			} }
		>
			{ sprintf(
				/* translators: %s: Error message - used in development mode */
				__(
					'Development error caught by error boundary: %s',
					'woocommerce-payments'
				),
				error.toString()
			) }
		</div>
	);
};

class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );

		this.state = {
			error: null,
		};
	}

	static getDerivedStateFromError( error ) {
		return { error };
	}

	componentDidCatch( error, info ) {
		// this branch of code will not be present in a production build
		if ( process.env.MODE !== 'production' ) {
			// eslint-disable-next-line no-console
			console.error( error, info );
		}

		if ( this.props.onError ) {
			this.props.onError( error, info );
		}
	}

	render() {
		const { children, fallbackRender: Fallback = DevFallback } = this.props;

		if ( ! this.state.error ) {
			return children;
		}

		return <Fallback error={ this.state.error } />;
	}
}

export default ErrorBoundary;
