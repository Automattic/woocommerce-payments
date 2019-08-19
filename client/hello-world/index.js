/** @format */
/**
 * External dependencies
 */
import { Component } from '@wordpress/element';

/**
 * Internal dependencies
 */

class HelloWorld extends Component {
	render() {
		return (
			<div className="woocommerce-payments__section">
				{ this.props.children || 'Hello, world.' }
			</div>
		);
	}
}

export { HelloWorld };
