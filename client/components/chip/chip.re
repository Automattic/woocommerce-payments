type t =
  | Primary
  | Light
  | Warning
  | Alert
  | Default;

let chipClass = chipType =>
  switch (chipType) {
  | Default
  | Primary => "chip-primary"
  | Warning => "chip-warning"
  | Alert => "chip-alert"
  | Light => "chip-light"
  };

[@genType]
[@react.component]
let make = (~message="", ~chipType=Default, ~isCompat=false) => {
  let classNames = ["chip", chipType->chipClass, isCompat ? "is-compat" : ""];

  <span
    className={
      classNames
      ->Belt.List.reduce("", (acc, curr) => acc ++ " " ++ curr)
      ->String.trim
    }>
    message->React.string
  </span>;
};
