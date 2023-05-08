/**
 * External dependencies
 */
import { useEffect, useRef, useState } from 'react';

// hook for handling API calls to get and create woopay user.
const useWooPayUser = () => {
	const [ isRegisteredUser, setIsRegisteredUser ] = useState( false );
	const windowRef = useRef( window );

	useEffect( () => {
		const handleWooPayUserCheck = ( e ) => {
			setIsRegisteredUser( e.detail.isRegisteredUser );
		};

		const currentWindowRef = windowRef.current;
		currentWindowRef.addEventListener(
			'woopayUserCheck',
			handleWooPayUserCheck
		);

		return () => {
			currentWindowRef.removeEventListener(
				'woopayUserCheck',
				handleWooPayUserCheck
			);
		};
	}, [] );

	return isRegisteredUser;
};

export default useWooPayUser;
