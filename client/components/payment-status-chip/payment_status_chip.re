[@genType.import "@wordpress/i18n"]
external __: (string, string) => string = "__";

type statusInfo = {
  chipType: Chip.t,
  message: string,
};

let statusInfo = status =>
  switch (
    (status: Belt.Result.t(Util.paymentStatus, Util.paymentStatusError))
  ) {
  | Error(_) => {chipType: Light, message: ""}
  | Ok(PartiallyRefunded) => {
      chipType: Light,
      message: __("Partial Refund", "woocommerce-payments"),
    }
  | Ok(FullyRefunded) => {
      chipType: Light,
      message: __("Refunded", "woocommerce-payments"),
    }
  | Ok(Paid) => {
      chipType: Light,
      message: __("Paid", "woocommerce-payments"),
    }
  | Ok(Authorized) => {
      chipType: Primary,
      message: __("Payment Authorized", "woocommerce-payments"),
    }
  | Ok(Failed) => {
      chipType: Alert,
      message: __("Payment failed", "woocommerce-payments"),
    }
  | Ok(Blocked) => {
      chipType: Alert,
      message: __("Payment blocked", "woocommerce-payments"),
    }
  | Ok(DisputeNeedsResponse) => {
      chipType: Primary,
      message: __("Disputed: Needs response", "woocommerce-payments"),
    }
  | Ok(DisputeUnderReview) => {
      chipType: Light,
      message: __("Disputed: In review", "woocommerce-payments"),
    }
  | Ok(DisputeWon) => {
      chipType: Light,
      message: __("Disputed: Won", "woocommerce-payments"),
    }
  | Ok(DisputeLost) => {
      chipType: Light,
      message: __("Disputed: Lost", "woocommerce-payments"),
    }
  | Ok(Disputed) => {chipType: Light, message: ""}
  };

[@genType]
[@react.component]
let make = (~charge) => {
  let statusInfo = charge->Util.getChargeStatus->statusInfo;

  <Chip message={statusInfo.message} chipType={statusInfo.chipType} />;
};
