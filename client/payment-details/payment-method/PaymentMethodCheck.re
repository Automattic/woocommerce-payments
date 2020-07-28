[@genType.import "@wordpress/i18n"]
external __: (string, string) => string = "__";

type checkedType =
  | Passed
  | Failed
  | Unavailable
  | NotChecked;

[@genType]
[@react.component]
let make = (~checked=NotChecked) => {
  switch (checked) {
  | Passed => __("Passed", "woocommerce-payments")->React.string
  | Failed => __("Failed", "woocommerce-payments")->React.string
  | Unavailable => __("Unavailable", "woocommerce-payments")->React.string
  | NotChecked => __("Not checked", "woocommerce-payments")->React.string
  };
};
