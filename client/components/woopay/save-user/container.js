/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const Container = ( { children, isBlocksCheckout } ) => {
	if ( ! isBlocksCheckout ) return children;
	return (
		<>
			<div className="woopay-save-new-user-container">
				<h2>{ __( 'Save my info' ) }</h2>
				{ children }
			</div>
		</>
	);
};

export default Container;
