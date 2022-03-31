/**
 * External dependencies
 */
import { useEffect, useState } from 'react';

// hook for checking the validity of phone number.
const usePhoneNumberValidity = ( element, phoneNumber ) => {
	const [ isValidNumber, setIsValidNumber ] = useState( false );

	useEffect( () => {
		const checkValidity = () => {
			const instance = window.intlTelInputGlobals.getInstance( element );
			setIsValidNumber( instance.isValidNumber() );
		};

		if ( element ) {
			checkValidity();
			element.addEventListener( 'countrychange', checkValidity );
		}

		return () => {
			if ( element ) {
				element.removeEventListener( 'countrychange', checkValidity );
			}
		};
	}, [ element, phoneNumber ] );

	return {
		isValidNumber,
	};
};

export default usePhoneNumberValidity;
