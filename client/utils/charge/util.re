let failedOutcomeTypes = ["issuer_declined", "invalid"];
let blockedOutcomeTypes = ["blocked"];

let getChargeOutcomeType = (charge: Types.Charge.charge) => {
  switch (charge.outcome) {
  | None => ""
  | Some(o) => o.type_
  };
};

let isChargeBlocked = (charge: Types.Charge.charge) => {
  "failed" == charge.status
  && List.exists(t => charge->getChargeOutcomeType == t, blockedOutcomeTypes);
};

let isChargeFailed = (charge: Types.Charge.charge) => {
  "failed" == charge.status
  && List.exists(t => charge->getChargeOutcomeType == t, failedOutcomeTypes);
};

let isChargeDisputed = (charge: Types.Charge.charge) => {
  charge.disputed === true;
};

let isChargeRefunded = (charge: Types.Charge.charge) =>
  charge.amount_refunded > 0;

let isChargeFullyRefunded = (charge: Types.Charge.charge) => charge.refunded;

let isChargePartiallyRefunded = (charge: Types.Charge.charge) =>
  charge->isChargeRefunded && !charge->isChargeFullyRefunded;

let isChargeSuccessful = (charge: Types.Charge.charge) => {
  "succeeded" == charge.status && charge.paid;
};

type paymentStatus =
  | Failed
  | Blocked
  | DisputeNeedsResponse
  | DisputeUnderReview
  | DisputeWon
  | DisputeLost
  | Disputed
  | PartiallyRefunded
  | FullyRefunded
  | Paid
  | Authorized;

type paymentStatusError =
  | NoPaymentStatus;

let mapDisputeStatusToChargeStatus = status => {
  switch (status) {
  | "warning_needs_response"
  | "needs_response" => DisputeNeedsResponse
  | "warning_under_review"
  | "under_review" => DisputeUnderReview
  | "won" => DisputeWon
  | "lost" => DisputeLost
  | _ => Disputed
  };
};

let getChargeStatus = charge =>
  if (charge->isChargeFailed) {
    Failed->Ok;
  } else if (isChargeBlocked(charge)) {
    Blocked->Ok;
  } else if (charge.disputed) {
    switch (charge.dispute) {
    | None => Disputed->Ok
    | Some(d) => d.status->mapDisputeStatusToChargeStatus->Ok
    };
  } else if (charge->isChargePartiallyRefunded) {
    PartiallyRefunded->Ok;
  } else if (charge->isChargeFullyRefunded) {
    FullyRefunded->Ok;
  } else if (charge->isChargeSuccessful) {
    charge.captured ? Paid->Ok : Authorized->Ok;
  } else {
    NoPaymentStatus->Error;
  };
