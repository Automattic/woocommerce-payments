type chargeStateFunctions = {
  getCharge: (. Types.Charge.id) => option(Types.Charge.charge),
  getChargeError: (. Types.Charge.id) => option(Types.Charge.chargeError),
  isResolving: (. string, array(Types.Charge.id)) => bool,
};

[@genType.import "@wordpress/data"]
external useSelect:
  (
    (string => chargeStateFunctions) => Types.Charge.chargeRequest,
    array(Types.Charge.id)
  ) =>
  Types.Charge.chargeRequest =
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
