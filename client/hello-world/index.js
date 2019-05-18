/** @format */
/**
 * External dependencies
 */
import { cloneElement, Component } from '@wordpress/element';

/**
 * Internal dependencies
 */

class HelloWorld extends Component {
	render() {
		return (
			<div className="woocommerce-payments__section">
       			Hello, world.
    		</div>
		);
	}
}

export { HelloWorld };