/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const Container = ( { children, isBlocksCheckout } ) => {
	if ( ! isBlocksCheckout ) return children;
	return (
		<>
			<div className="woopay-save-new-user-container">
				<div className="wc-block-components-checkout-step__heading-container">
					<div className="wc-block-components-checkout-step__heading">
						<h2 className="wc-block-components-title wc-block-components-checkout-step__title">
							{ __( 'Save my info' ) }
						</h2>
					</div>
				</div>
				{ children }
			</div>
		</>
	);
};

export default Container;
