/* Untyped file generated from actions.re by genType. */
/* eslint-disable */

import * as Curry from 'bs-platform/lib/es6/curry.js';

import * as actionsBS from './actions.bs';

export const updateCharge = function ( Arg1, Arg2 ) {
	const result = Curry._2( actionsBS.updateCharge, Arg1, Arg2 );
	return result;
};

export const updateErrorForCharge = function ( Arg1, Arg2, Arg3 ) {
	const result = Curry._3(
		actionsBS.updateErrorForCharge,
		Arg1,
		Arg2 == null ? undefined : Arg2,
		Arg3 == null ? undefined : Arg3
	);
	return result;
};
