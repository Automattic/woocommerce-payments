[@genType]
let updateCharge = (id, data): ChargeReducer.Event.t => {
  type_: ChargeReducer.Event.reducerTypeToJs(`SetCharge),
  id,
  data: (data |> Decode.charge)->Some,
  error: None,
};

[@genType]
let updateErrorForCharge = (id, data, error): ChargeReducer.Event.t => {
  type_: ChargeReducer.Event.reducerTypeToJs(`SetErrorForCharge),
  id,
  data,
  error,
};
