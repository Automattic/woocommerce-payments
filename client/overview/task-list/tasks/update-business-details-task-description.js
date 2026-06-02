/** @format **/

/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';

const loadErrorMessage = async ( error ) => {
	const { default: strings } = await import( '../strings' );

	return strings.errors[ error.code ] || error.reason;
};

export const loadRequirementErrorMessages = async ( requirementErrors ) => {
	const messages = await Promise.all(
		requirementErrors.map( ( error ) => loadErrorMessage( error ) )
	);

	return Array.from( new Set( messages ) );
};

const UpdateBusinessDetailsTaskDescription = ( {
	error,
	updateByDescription,
} ) => {
	const [ message, setMessage ] = useState( error.reason );

	useEffect( () => {
		let isMounted = true;

		loadErrorMessage( error ).then( ( loadedMessage ) => {
			if ( isMounted ) {
				setMessage( loadedMessage );
			}
		} );

		return () => {
			isMounted = false;
		};
	}, [ error ] );

	return React.createElement(
		React.Fragment,
		null,
		message,
		updateByDescription ? ` ${ updateByDescription }` : null
	);
};

export { UpdateBusinessDetailsTaskDescription };
export default UpdateBusinessDetailsTaskDescription;
