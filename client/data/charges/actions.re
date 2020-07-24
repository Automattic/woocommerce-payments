let updateCharge = (id, data): Types.ChargeReducer.Event.t => {
  type_: "SET_CHARGE",
  id,
  data: (data |> Decode.charge)->Some,
  error: None,
};

let updateErrorForCharge = (id, data, error): Types.ChargeReducer.Event.t => {
  type_: "SET_ERROR_FOR_CHARGE",
  id,
  data,
  error,
};
