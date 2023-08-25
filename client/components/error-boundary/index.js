/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import BannerNotice from 'components/banner-notice';

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
			<BannerNotice status="error" isDismissible={ false }>
				{ __(
					'There was an error rendering this view. Please contact support for assistance if the problem persists.',
					'woocommerce-payments'
				) }
				<br />
				{ this.state.error.toString() }
			</BannerNotice>
		);
	}
}

export default ErrorBoundary;
