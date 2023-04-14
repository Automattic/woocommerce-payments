/**
 * External dependencies
 */
import { useRef } from 'react';
import { uniqueId } from 'lodash';

export const useUniqueId = ( prefix = '' ): string => {
	const ref = useRef< string >();
	if ( ! ref.current ) {
		ref.current = uniqueId( prefix );
	}
	return ref.current;
};
