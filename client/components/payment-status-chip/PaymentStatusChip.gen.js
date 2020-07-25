/* Untyped file generated from PaymentStatusChip.re by genType. */
/* eslint-disable */

import {__ as __NotChecked} from '@wordpress/i18n';

import * as React from 'react';

const $$toRE161077946 = {"Failed": 0, "Blocked": 1, "PartiallyRefunded": 2, "FullyRefunded": 3, "Paid": 4, "Authorized": 5};

const $$toRE701299253 = {"WarningNeedsResponse": 0, "WarningUnderReview": 1, "WarningClosed": 2, "NeedsResponse": 3, "UnderReview": 4, "ChargeRefunded": 5, "Won": 6, "Lost": 7, "NotDisputed": 8};

// In case of type error, check the type of '__' in 'PaymentStatusChip.re' and '@wordpress/i18n'.
export const __TypeChecked = __NotChecked;

// Export '__' early to allow circular import from the '.bs.js' file.
export const __ = __TypeChecked;

const PaymentStatusChipBS = require('./PaymentStatusChip.bs');

export const getChipType = function (Arg1) {
  const result = PaymentStatusChipBS.getChipType(typeof(Arg1) === 'object'
    ? {TAG: 0, _0:$$toRE701299253[Arg1.value]}
    : $$toRE161077946[Arg1]);
  return result
};

export const getChipMessage = function (Arg1) {
  const result = PaymentStatusChipBS.getChipMessage(typeof(Arg1) === 'object'
    ? {TAG: 0, _0:$$toRE701299253[Arg1.value]}
    : $$toRE161077946[Arg1]);
  return result
};

export const make = function PaymentStatusChip(Arg1) {
  const $props = {status:typeof(Arg1.status) === 'object'
    ? {TAG: 0, _0:$$toRE701299253[Arg1.status.value]}
    : $$toRE161077946[Arg1.status]};
  const result = React.createElement(PaymentStatusChipBS.make, $props);
  return result
};

export const $$default = function PaymentStatusChip(Arg1) {
  const $props = {status:typeof(Arg1.status) === 'object'
    ? {TAG: 0, _0:$$toRE701299253[Arg1.status.value]}
    : $$toRE161077946[Arg1.status]};
  const result = React.createElement(PaymentStatusChipBS.default, $props);
  return result
};

export default $$default;
