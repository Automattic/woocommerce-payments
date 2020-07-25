[@genType.import "@wordpress/i18n"]
external __: (string, string) => string = "__";

[@genType]
let getChipType = status =>
  switch (status) {
  | PaymentStatus.Failed
  | Blocked => Chip.Alert
  | Disputed(WarningNeedsResponse)
  | Disputed(NeedsResponse)
  | Authorized => Primary
  | _ => Light
  };

[@genType]
let getChipMessage = status =>
  switch (status) {
  | PaymentStatus.Failed => __("Payment failed", "woocommerce-payments")
  | Blocked => __("Payment blocked", "woocommerce-payments")
  | Disputed(WarningNeedsResponse) =>
    __("Inquiry: Needs response", "woocommerce-payments")
  | Disputed(WarningUnderReview) =>
    __("Inquiry: Under review", "woocommerce-payments")
  | Disputed(WarningClosed) => __("Inquiry: Closed", "woocommerce-payments")
  | Disputed(NeedsResponse) => __("Needs response", "woocommerce-payments")
  | Disputed(UnderReview) => __("Under review", "woocommerce-payments")
  | Disputed(ChargeRefunded) => __("Charge refunded", "woocommerce-payments")
  | Disputed(Won) => __("Won", "woocommerce-payments")
  | Disputed(Lost) => __("Lost", "woocommerce-payments")
  | PartiallyRefunded => __("Partial refund", "woocommerce-payments")
  | FullyRefunded => __("Refunded", "woocommerce-payments")
  | Paid => __("Paid", "woocommerce-payments")
  | Authorized => __("Payment authorized", "woocommerce-payments")
  | Disputed(NotDisputed) => ""
  };

[@genType]
[@react.component]
let make = (~status: PaymentStatus.t) => {
  let (chipType, message) = (status->getChipType, status->getChipMessage);

  <Chip message chipType />;
};

[@genType]
let default = make;
