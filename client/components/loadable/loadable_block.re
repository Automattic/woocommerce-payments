[@genType]
[@react.component]
let make =
    (
      ~numLines=1,
      ~isLoading: bool,
      ~value: option(React.element)=?,
      ~children,
    ) => {
  let placeholder =
    <p style={ReactDOM.Style.make(~lineHeight=numLines->string_of_int, ())}>
      "Block placeholder"->React.string
    </p>
    ->Some;
  <Loadable isLoading value placeholder display="block"> children </Loadable>;
};

[@genType]
let default = make;
