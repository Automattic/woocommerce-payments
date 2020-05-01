/** @format **/

/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';
import { getHistory } from '@woocommerce/navigation';

/**
 * Hook for displaying an optional confirmation message.
 *
 * Usage:
 * - useConfirmNavigation( () => 'Are you sure you want to leave?' );
 * - useConfirmNavigation( saved => { if ( ! saved ) return 'Discard unsaved changes?' }, [ saved ] );
 *
 * @param {Function} getMessage returns confirmation message string if one should appear
 * @param {Array} deps effect dependencies
 */
const useConfirmNavigation = ( getMessage, deps ) => {
	useEffect( () => {
		const message = getMessage();
		if ( ! message ) {
			return;
		}

		const handler = event => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', handler );
		const unblock = getHistory().block( message );

		return () => {
			window.removeEventListener( 'beforeunload', handler );
			unblock();
		};
	}, deps );
};

export default useConfirmNavigation;
