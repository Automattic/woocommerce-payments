let getChargeState = (state: Types.ChargeReducer.State.t, id) =>
  state->Belt.Map.String.getWithDefault(id, {data: None, error: None});

let updateCharge = (state: Types.ChargeReducer.State.t, id, data) => {
  state->Belt.Map.String.set(id, {...state->getChargeState(id), data});
};

let updateChargeError = (state: Types.ChargeReducer.State.t, id, error) => {
  state->Belt.Map.String.set(id, {...state->getChargeState(id), error});
};

// We use this to initialize the state.
let getState = state => {
  switch (state) {
  | None => [||]->Belt.Map.String.fromArray
  | Some(s) => s
  };
};

let receiveCharges = (state, event: Types.Reducer.event) => {
  switch (event.type_) {
  | "SET_CHARGE" => state->getState->updateCharge(event.id, event.data)
  | "SET_ERROR_FOR_CHARGE" =>
    state->getState->updateChargeError(event.id, event.error)
  | _ => state->getState
  };
};

[@genType]
let default = receiveCharges;
