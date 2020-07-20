let getChargeState = (state: Types.Charge.Reducer.state, id) => Belt.Map.String.getWithDefault(state, id, {data: None, error: None});

let updateCharge = (state: Types.Charge.Reducer.state, id, data) => {
  Belt.Map.String.set(state, id, {...getChargeState(state, id), data: data})
};

let updateChargeError = (state: Types.Charge.Reducer.state, id, error) => {
  Belt.Map.String.set(state, id, {...getChargeState(state, id), error: error})
};

// We use this to initialize the state.
let getState = state => {
  switch (state) {
  | None => Belt.Map.String.fromArray([||])
  | Some(s) => s
  };
};

let receiveCharges = (state, event: Types.Reducer.event) => {
  switch (event.type_) {
  | "SET_CHARGE" => getState(state)->updateCharge(event.id, event.data)
  | "SET_ERROR_FOR_CHARGE" => getState(state)->updateChargeError(event.id, event.error)
  | _ => getState(state)
  };
};
