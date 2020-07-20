let getCharge = (state: Types.Reducer.state, id: Types.Charge.id) => {
  switch (Js.Dict.get(state.charges, id)) {
  | None => None
  | Some(data) => Some(data.data)
  };
};

let getChargeError = (state: Types.Reducer.state, id: Types.Charge.id) => {
  switch (Js.Dict.get(state.charges, id)) {
  | None => None
  | Some(data) => Some(data.error)
  };
};
