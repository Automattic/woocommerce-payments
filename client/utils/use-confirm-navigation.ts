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
 */
// eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types
const useConfirmNavigation = () => {
	const [ message, setMessage ] = useState( '' );

	useEffect( () => {
		if ( ! message ) {
			return;
		}

		const handler = ( event: Event ) => {
			event.preventDefault();
			event.returnValue = false;
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
