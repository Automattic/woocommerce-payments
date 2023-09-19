/**
 * Internal dependencies
 */
import logoImg from 'assets/images/logo.svg?asset';

export default ( props ) => (
	<img
		src={ logoImg }
		width="241"
		height="64"
		alt={ 'WooPayments logo' }
		{ ...props }
	/>
);
