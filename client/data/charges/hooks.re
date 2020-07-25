type chargeStateFunctions = {
  getCharge: (. string) => option(Charge.Request.t),
  getChargeError: (. string) => option(Charge.RequestError.t),
  isResolving: (. string, array(string)) => bool,
};

[@genType.import "@wordpress/data"]
external useSelect:
  ((string => chargeStateFunctions) => Charge.Request.t, array(string)) =>
  Charge.Request.t =
  "useSelect";

[@genType]
let useCharge = chargeId =>
  useSelect(
    select => {
      let {getCharge, getChargeError, isResolving} =
        select(Constants.storeName);
      {
        charge: getCharge(. chargeId),
        chargeError: getChargeError(. chargeId),
        isLoading: isResolving(. "getCharge", [|chargeId|]),
      };
    },
    [|chargeId|],
  );
