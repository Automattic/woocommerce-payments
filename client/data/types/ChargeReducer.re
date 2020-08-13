module Event = {
  [@bs.deriving jsConverter]
  type reducerType = [ | `SetCharge | `SetErrorForCharge];

  type t = {
    [@bs.as "type"]
    type_: string,
    id: string,
    data: option(Charge.t),
    error: option(Charge.RequestError.t),
  };
};

type chargeStateEntry = {
  data: option(Charge.t),
  error: option(Charge.RequestError.t),
};

module State = {
  type t = Belt.Map.String.t(chargeStateEntry);
};
