/**
 * External dependencies
 */
import { useEffect, useRef, useState } from 'react';

// hook for handling API calls to get and create platform checkout user.
const usePlatformCheckoutUser = () => {
	const [ isRegisteredUser, setIsRegisteredUser ] = useState( false );
	const windowRef = useRef( window );

	useEffect( () => {
		const handlePlatformCheckoutUserCheck = ( e ) => {
			setIsRegisteredUser( e.detail.isRegisteredUser );
		};

		const currentWindowRef = windowRef.current;
		currentWindowRef.addEventListener(
			'PlatformCheckoutUserCheck',
			handlePlatformCheckoutUserCheck
		);

		return () => {
			currentWindowRef.removeEventListener(
				'PlatformCheckoutUserCheck',
				handlePlatformCheckoutUserCheck
			);
		};
	}, [] );

	return isRegisteredUser;
};

export default usePlatformCheckoutUser;
