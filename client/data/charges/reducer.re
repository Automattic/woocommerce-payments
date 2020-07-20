let updateCharge = (state: Types.Charge.Reducer.state, id, data) => {
  let currentCharge =
    switch (Js.Dict.get(state, id)) {
    | Some(s) => s
    | None => {data: None, error: None}
    };
  Js.Dict.set(state, id, {...currentCharge, data});
  state;
};

let updateChargeError = (state: Types.Charge.Reducer.state, id, error) => {
  let currentCharge =
    switch (Js.Dict.get(state, id)) {
    | Some(s) => s
    | None => {data: None, error: None}
    };
  Js.Dict.set(state, id, {...currentCharge, error});
  state;
};

// We use this to initialize the state.
let getState = state => {
  switch (state) {
  | None => Js.Dict.fromList([])
  | Some(s) => s
  };
};

let receiveCharges = (state, event: Types.Reducer.event) => {
  event |> Js.log;
  switch (event.type_) {
  | "SET_CHARGE" => getState(state)->updateCharge(event.id, event.data)
  | "SET_ERROR_FOR_CHARGE" => getState(state)->updateChargeError(event.id, event.error)
  | _ => getState(state)
  };
};
