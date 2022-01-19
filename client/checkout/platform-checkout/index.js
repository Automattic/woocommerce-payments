/**
 * External dependencies
 */
import ReactDOM from 'react-dom';

const checkoutPageSaveUserContainer = document.getElementById(
	'checkout-page-save-user-container'
);

if ( checkoutPageSaveUserContainer ) {
	ReactDOM.render(
		<div>Remember your details?</div>,
		checkoutPageSaveUserContainer
	);
}
