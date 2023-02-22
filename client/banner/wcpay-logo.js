/**
 * Internal dependencies
 */
import LogoImg from 'assets/images/logo.svg';

export default ( props ) => (
	<LogoImg
		width="241"
		height="64"
		alt={ 'WooCommerce Payments logo' }
		{ ...props }
	/>
);
