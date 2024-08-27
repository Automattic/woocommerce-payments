/**
 * Internal dependencies
 */
import logoImg from 'assets/images/logo.svg?asset';
import { __ } from '@wordpress/i18n';

export default ( props ) => (
	<img
		src={ logoImg }
		width="241"
		height="64"
		alt={ __( 'WooPayments logo', 'woocommerce-payments' ) }
		{ ...props }
	/>
);
