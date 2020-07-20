type statusInfo = {
  chipType: string,
  message: string,
};

let statusInfo = status =>
  switch (
    (status: Belt.Result.t(Util.paymentStatus, Util.paymentStatusError))
  ) {
  | Error(_) => {chipType: "light", message: ""}
  | Ok(PartiallyRefunded) => {chipType: "light", message: "Partial Refund"}
  | Ok(FullyRefunded) => {chipType: "light", message: "Refunded"}
  | Ok(Paid) => {chipType: "light", message: "Paid"}
  | Ok(Authorized) => {chipType: "primary", message: "Payment Authorized"}
  | Ok(Failed) => {chipType: "alert", message: "Payment failed"}
  | Ok(Blocked) => {chipType: "alert", message: "Payment blocked"}
  | Ok(DisputeNeedsResponse) => {
      chipType: "primary",
      message: "Disputed: Needs response",
    }
  | Ok(DisputeUnderReview) => {
      chipType: "light",
      message: "Disputed: In review",
    }
  | Ok(DisputeWon) => {chipType: "light", message: "Disputed: Won"}
  | Ok(DisputeLost) => {chipType: "light", message: "Disputed: Lost"}
  | Ok(Disputed) => {chipType: "light", message: ""}
  };

[@react.component]
let make = (~charge) => {
  let statusInfo = charge->Util.getChargeStatus->statusInfo;

  <Chip message={statusInfo.message} chipType={statusInfo.chipType} />;
};
