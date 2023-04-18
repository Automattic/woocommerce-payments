/**
 * External dependencies
 */
import { useEffect, useRef, useState } from 'react';

// hook for handling API calls to get and create platform checkout user.
const useWooPayUser = () => {
	const [ isRegisteredUser, setIsRegisteredUser ] = useState( false );
	const windowRef = useRef( window );

	useEffect( () => {
		const handleWooPayUserCheck = ( e ) => {
			setIsRegisteredUser( e.detail.isRegisteredUser );
		};

		const currentWindowRef = windowRef.current;
		currentWindowRef.addEventListener(
			'WooPayUserCheck',
			handleWooPayUserCheck
		);

		return () => {
			currentWindowRef.removeEventListener(
				'WooPayUserCheck',
				handleWooPayUserCheck
			);
		};
	}, [] );

	return isRegisteredUser;
};

export default useWooPayUser;
