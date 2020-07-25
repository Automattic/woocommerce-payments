/* Untyped file generated from PaymentStatus.re by genType. */
/* eslint-disable */

const $$toJS161077946 = {
	'0': 'Failed',
	'1': 'Blocked',
	'2': 'PartiallyRefunded',
	'3': 'FullyRefunded',
	'4': 'Paid',
	'5': 'Authorized',
};

const $$toJS701299253 = {
	'0': 'WarningNeedsResponse',
	'1': 'WarningUnderReview',
	'2': 'WarningClosed',
	'3': 'NeedsResponse',
	'4': 'UnderReview',
	'5': 'ChargeRefunded',
	'6': 'Won',
	'7': 'Lost',
	'8': 'NotDisputed',
};

import * as PaymentStatusBS from './PaymentStatus.bs';

export const fromCharge = function ( Arg1 ) {
	const result = PaymentStatusBS.fromCharge( Arg1 );
	return typeof result === 'object'
		? { tag: 'Disputed', value: $$toJS701299253[ result._0 ] }
		: $$toJS161077946[ result ];
};
