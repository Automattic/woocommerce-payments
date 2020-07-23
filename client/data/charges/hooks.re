type chargeStateFunctions = {
  getCharge: (. string) => option(Types.Charge.Request.t),
  getChargeError: (. string) => option(Types.Charge.RequestError.t),
  isResolving: (. string, array(string)) => bool,
};

[@genType.import "@wordpress/data"]
external useSelect:
  (
    (string => chargeStateFunctions) => Types.Charge.Request.t,
    array(string)
  ) =>
  Types.Charge.Request.t =
  "useSelect";

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
