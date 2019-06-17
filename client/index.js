/**
 * Internal dependencies
 */
import './style.scss';
import { render } from '@wordpress/element';
import { HelloWorld } from 'hello-world';

const rootElem = document.getElementById( 'woocommerce-payments__root' );
const viewType = rootElem.className;

render(
	<HelloWorld/>,
	document.getElementById( 'woocommerce-payments__root' )
);
