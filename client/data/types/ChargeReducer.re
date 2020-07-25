module Event = {
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
