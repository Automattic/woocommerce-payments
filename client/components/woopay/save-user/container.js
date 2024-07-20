/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const Container = ( {
	children,
	isBlocksCheckout,
	wcVersionGreaterThan91,
} ) => {
	if ( ! isBlocksCheckout ) return children;
	return (
		<>
			<div
				className={ `woopay-save-new-user-container ${
					wcVersionGreaterThan91 ? 'wc-version-greater-than-91' : ''
				}` }
			>
				<h2>{ __( 'Save my info' ) }</h2>
				{ children }
			</div>
		</>
	);
};

export default Container;
