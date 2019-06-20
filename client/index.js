/**
 * Internal dependencies
 */
import './style.scss';
import { render } from '@wordpress/element';
import { HelloWorld } from 'hello-world';

render(
	<HelloWorld/>,
	document.getElementById( 'woocommerce-payments__root' )
);
