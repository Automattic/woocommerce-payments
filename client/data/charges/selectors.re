let getCharge = (state: Types.Reducer.state, id: Types.Charge.id) => {
  Belt.Map.String.getWithDefault(
    state.charges,
    id,
    {data: None, error: None},
  ).
    data;
};

let getChargeError = (state: Types.Reducer.state, id: Types.Charge.id) => {
  Belt.Map.String.getWithDefault(
    state.charges,
    id,
    {data: None, error: None},
  ).
    error;
};
