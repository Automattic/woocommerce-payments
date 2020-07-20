let types = ["primary", "light", "warning", "alert"];

[@react.component]
let default = (~message, ~chipType, ~isCompat=false) => {
  let classNames = [|
    "chip",
    "chip-" ++ (types |> List.exists(t => chipType == t) ? chipType : "primary"),
    isCompat ? "is-compat" : "",
  |];

  <span
    className={
      classNames
      |> Array.fold_left((acc, curr) => acc ++ " " ++ curr, "")
      |> String.trim
    }>
    {ReasonReact.string(message)}
  </span>;
};
