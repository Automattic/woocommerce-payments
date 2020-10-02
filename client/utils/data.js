/** @format */

export const getResourceId = ( identifier ) => {
	return JSON.stringify( identifier, Object.keys( identifier ).sort() );
};
