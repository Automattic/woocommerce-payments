[@genType]
let updateCharge = (id, data): ChargeReducer.Event.t => {
  type_: "SET_CHARGE",
  id,
  data: (data |> Decode.charge)->Some,
  error: None,
};

[@genType]
let updateErrorForCharge = (id, data, error): ChargeReducer.Event.t => {
  type_: "SET_ERROR_FOR_CHARGE",
  id,
  data,
  error,
};
