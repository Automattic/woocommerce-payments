/**
 * Internal dependencies
 */
import logoImg from '../../assets/images/logo.svg';

export default ( props ) => (
	<img
		width="241"
		height="64"
		src={ logoImg }
		alt={ 'WooCommerce Payments logo' }
		{ ...props }
	/>
);
