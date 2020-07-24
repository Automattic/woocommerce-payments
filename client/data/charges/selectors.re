[@genType]
let getCharge = (state: Types.Reducer.state, id: string) => {
  state.charges
  ->Belt.Map.String.getWithDefault(
      id,
      {data: Types.Charge.make()->Some, error: None},
    ).
    data;
};

[@genType]
let getChargeError = (state: Types.Reducer.state, id: string) => {
  state.charges
  ->Belt.Map.String.getWithDefault(id, {data: None, error: None}).
    error;
};
