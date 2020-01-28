/** @format */

export const getResourceId = ( prefix, identifier ) => {
	const idString = JSON.stringify( identifier, Object.keys( identifier ).sort() );
	return `${ prefix }:${ idString }`;
};
