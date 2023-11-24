/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const Container = ( { children, isBlocksCheckout } ) => {
	if ( ! isBlocksCheckout ) return children;
	return (
		<>
			<legend className="screen-reader-text">
				{ __( 'Remember me', 'woocommerce-payments' ) }
			</legend>
			<div className="wc-block-components-checkout-step__heading">
				<h2
					className="wc-block-components-title wc-block-components-checkout-step__title"
					aria-hidden={ true }
				>
					{ __( 'Remember me', 'woocommerce-payments' ) }
				</h2>
			</div>
			<div className="wc-block-components-checkout-step__container">
				<div className="wc-block-components-checkout-step__content">
					<div className="woopay-save-new-user-container">
						{ children }
					</div>
				</div>
			</div>
		</>
	);
};

export default Container;
