/* Untyped file generated from util.re by genType. */
/* eslint-disable */

const $$toJS40962700 = {
	'0': 'Failed',
	'1': 'Blocked',
	'2': 'DisputeNeedsResponse',
	'3': 'DisputeUnderReview',
	'4': 'DisputeWon',
	'5': 'DisputeLost',
	'6': 'Disputed',
	'7': 'PartiallyRefunded',
	'8': 'FullyRefunded',
	'9': 'Paid',
	'10': 'Authorized',
};

import * as utilBS from './util.bs';

export const getChargeStatus = function ( Arg1 ) {
	const result = utilBS.getChargeStatus( Arg1 );
	return result.TAG === 0
		? { tag: 'Ok', value: $$toJS40962700[ result._0 ] }
		: { tag: 'Error', value: 'NoPaymentStatus' };
};
