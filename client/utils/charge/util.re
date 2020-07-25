let failedOutcomeTypes = [OutcomeType.IssuerDeclined, Invalid];
let blockedOutcomeTypes = [OutcomeType.Blocked];

let extractOutcomeType = (charge: Charge.t) =>
  switch (charge.outcome) {
  | Some(o) => o.type_
  | None => Invalid
  };

let isChargeBlocked = (charge: Charge.t) => {
  ChargeStatus.Failed == charge.status
  && List.exists(t => charge->extractOutcomeType == t, blockedOutcomeTypes);
};

let isChargeFailed = (charge: Charge.t) => {
  ChargeStatus.Failed == charge.status
  && List.exists(t => charge->extractOutcomeType == t, failedOutcomeTypes);
};

let isChargeDisputed = (charge: Charge.t) => {
  charge.disputed === true;
};

let isChargeRefunded = (charge: Charge.t) => charge.amount_refunded > 0;

let isChargeFullyRefunded = (charge: Charge.t) => charge.refunded;

let isChargePartiallyRefunded = (charge: Charge.t) =>
  charge->isChargeRefunded && !charge->isChargeFullyRefunded;

let isChargeSuccessful = (charge: Charge.t) => {
  ChargeStatus.Succeeded == charge.status && charge.paid;
};

type paymentStatusError =
  | NoPaymentStatus(string);

let getDisputeStatus = status => {
  switch (status) {
  | "warning_needs_response" => DisputeStatus.WarningNeedsResponse
  | "warning_under_review" => WarningUnderReview
  | "warning_closed" => WarningClosed
  | "needs_response" => NeedsResponse
  | "under_review" => UnderReview
  | "charge_refunded" => ChargeRefunded
  | "won" => Won
  | "lost" => Lost
  | _ => NotDisputed
  };
};

let getChargeStatus = status =>
  switch (status) {
  | "succeeded" => ChargeStatus.Succeeded
  | "failed" => Failed
  | "pending" => Pending
  | _ => Pending
  };

let getOutcomeType = type_ =>
  switch (type_) {
  | "authorized" => OutcomeType.Authorized
  | "manual_review" => ManualReview
  | "issuer_declined" => IssuerDeclined
  | "blocked" => Blocked
  | "invalid" => Invalid
  | _ => Invalid
  };
