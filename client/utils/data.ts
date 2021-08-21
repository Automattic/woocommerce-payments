/** @format */

export const getResourceId = (
	identifier: Record< string, string >
): string => {
	return JSON.stringify( identifier, Object.keys( identifier ).sort() );
};
