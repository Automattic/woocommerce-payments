/**
 * Checks if a semantic version string is greater than or equal to a base version.
 *
 * Supports semantic version strings like "1.2.3-beta" by ignoring pre-release tags.
 *
 * @param version Version that is compared.
 * @param base Version to compare with.
 * @return Whether version is greater than or equal to base.
 */
export const isVersionGreaterOrEqual = (
	version: string,
	base: string
): boolean => {
	const parse = ( v: string ) =>
		v.split( '-' )[ 0 ].split( '.' ).map( Number );
	const [ v1 = 0, v2 = 0, v3 = 0 ] = parse( version );
	const [ b1 = 0, b2 = 0, b3 = 0 ] = parse( base );
	return (
		v1 > b1 ||
		( v1 === b1 && v2 > b2 ) ||
		( v1 === b1 && v2 === b2 && v3 >= b3 )
	);
};
