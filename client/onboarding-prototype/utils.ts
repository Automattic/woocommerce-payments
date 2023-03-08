/**
 * External dependencies
 */
import { forEach, set } from 'lodash';

export const fromDotNotation = (
	records: Record< string, unknown >
): Record< string, unknown > => {
	const result = {};
	forEach(
		records,
		( value, key ) => value != null && set( result, key, value )
	);
	return result;
};
