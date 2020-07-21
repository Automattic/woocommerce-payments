type t =
  | Primary
  | Light
  | Warning
  | Alert
  | Default;

let chipClass = chipType => switch(chipType) {
  | Primary => "chip-primary"
  | Warning => "chip-warning"
  | Alert => "chip-alert"
  | Light
  | Default => "chip-light"
};

[@react.component]
let make = (~message, ~chipType=Default, ~isCompat=false) => {
  let classNames = [|
    "chip",
    chipClass(chipType),
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
