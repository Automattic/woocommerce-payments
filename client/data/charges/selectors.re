[@genType]
let getCharge = (state: Reducer.state, id: string) => {
  state.charges
  ->Belt.Map.String.getWithDefault(
      id,
      {data: Charge.make()->Some, error: None},
    ).
    data;
};

[@genType]
let getChargeError = (state: Reducer.state, id: string) => {
  state.charges
  ->Belt.Map.String.getWithDefault(id, {data: None, error: None}).
    error;
};
