/** @format **/

/**
 * External dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { getHistory } from '@woocommerce/navigation';

/**
 * Hook for displaying an optional confirmation message.
 *
 * Usage:
 * - const callback = useConfirmNavigation( () => 'Are you sure you want to leave?' );
 *   useEffect( callback , [ callback, otherDependency ] );
 *
 * @param {string} message the confirmation message string if one should appear
 * @return {Function} A setter for the navigation message
 */
const useConfirmNavigation = () => {
	const [ message, setMessage ] = useState( '' );

	useEffect( () => {
		if ( ! message ) {
			return;
		}

		const handler = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', handler );
		const unblock = getHistory().block( message );

		return () => {
			window.removeEventListener( 'beforeunload', handler );
			unblock();
		};
	}, [ message ] );

	return { setMessage };
};

export default useConfirmNavigation;
