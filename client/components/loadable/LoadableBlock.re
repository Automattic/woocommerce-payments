[@genType]
[@react.component]
let make =
    (~numLines=1, ~isLoading=false, ~value=React.null, ~children=React.null) => {
  let placeholder =
    <p style={ReactDOM.Style.make(~lineHeight=numLines->string_of_int, ())}>
      "Block placeholder"->React.string
    </p>;
  <Loadable isLoading value placeholder display="block"> children </Loadable>;
};

[@genType]
let default = make;
