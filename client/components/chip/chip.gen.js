/* Untyped file generated from chip.re by genType. */
/* eslint-disable */

import * as React from 'react';

const $$toRE454541293 = {
	Primary: 0,
	Light: 1,
	Warning: 2,
	Alert: 3,
	Default: 4,
};

import * as chipBS from './chip.bs';

export const chipClass = function ( Arg1 ) {
	const result = chipBS.chipClass( $$toRE454541293[ Arg1 ] );
	return result;
};

export const make = function chip( Arg1 ) {
	const $props = {
		chipType:
			Arg1.chipType == null
				? undefined
				: $$toRE454541293[ Arg1.chipType ],
		isCompat: Arg1.isCompat,
		message: Arg1.message,
	};
	const result = React.createElement( chipBS.make, $props );
	return result;
};
