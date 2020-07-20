let updateCharge = (id, data): Types.Charge.Reducer.event => {
  type_: "SET_CHARGE",
  id,
  data,
  error: None,
};

let updateErrorForCharge = (id, data, error): Types.Charge.Reducer.event => {
  type_: "SET_ERROR_FOR_CHARGE",
  id,
  data,
  error,
};
