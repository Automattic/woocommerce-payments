let getComponent = (~placeholder=None, ~children=None, ~value=None, ()) => {
  switch (placeholder, children, value) {
  | (Some(p), _, _) => p
  | (_, Some(c), _) => c
  | (_, _, Some(v)) => v
  | _ => React.null
  };
};

let getClass = display => {
  "is-loadable-placeholder" ++ ("" == display ? "" : " is-" ++ display);
};

[@genType]
[@react.component]
let make =
    (~isLoading=false, ~display="", ~placeholder=?, ~value=?, ~children=?) => {
  isLoading
    ? <span className={display->getClass} ariaBusy=true>
        {getComponent(~placeholder, ~children, ~value, ())}
      </span>
    : getComponent(~children, ~value, ());
};

[@genType]
let default = make;
