/**
 * Internal dependencies
 */
import './style.scss';
import { render } from '@wordpress/element';

render(
	<div className="woocommerce-payments__section">
        Hello, world.
    </div>,
	document.getElementById( 'woocommerce-payments__root' )
);
