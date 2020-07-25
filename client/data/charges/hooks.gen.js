/* Untyped file generated from hooks.re by genType. */
/* eslint-disable */

import {useSelect as useSelectNotChecked} from '@wordpress/data';

// In case of type error, check the type of 'useSelect' in 'hooks.re' and '@wordpress/data'.
export const useSelectTypeChecked = useSelectNotChecked;

// Export 'useSelect' early to allow circular import from the '.bs.js' file.
export const useSelect = function (Arg1, Arg2) {
  const result = useSelectTypeChecked(function (Arg11) {
      const result1 = Arg1(function (Arg12) {
          const result2 = Arg11(Arg12);
          return {getCharge:function (Arg13) {
              const result3 = result2.getCharge(Arg13);
              return (result3 == null ? undefined : result3)
            }, getChargeError:function (Arg14) {
              const result4 = result2.getChargeError(Arg14);
              return (result4 == null ? undefined : result4)
            }, isResolving:result2.isResolving}
        });
      return result1
    }, Arg2);
  return result
};

const hooksBS = require('./hooks.bs');

export const useCharge = hooksBS.useCharge;
