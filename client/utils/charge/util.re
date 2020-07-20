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
  && List.exists(t => getChargeOutcomeType(charge) == t, blockedOutcomeTypes);
};

let isChargeFailed = (charge: Types.Charge.charge) => {
  "failed" == charge.status
  && List.exists(t => getChargeOutcomeType(charge) == t, failedOutcomeTypes);
};

let isChargeDisputed = (charge: Types.Charge.charge) => {
  charge.disputed === true;
};

let isChargeRefunded = (charge: Types.Charge.charge) =>
  charge.amount_refunded > 0;

let isChargeFullyRefunded = (charge: Types.Charge.charge) => charge.refunded;

let isChargePartiallyRefunded = (charge: Types.Charge.charge) =>
  isChargeRefunded(charge) && !isChargeFullyRefunded(charge);

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
  if (isChargeFailed(charge)) {
    Ok(Failed);
  } else if (isChargeBlocked(charge)) {
    Ok(Blocked);
  } else if (charge.disputed) {
    switch (charge.dispute) {
    | None => Ok(Disputed)
    | Some(d) => d.status->mapDisputeStatusToChargeStatus->Ok
    };
  } else if (isChargePartiallyRefunded(charge)) {
    Ok(PartiallyRefunded);
  } else if (isChargeFullyRefunded(charge)) {
    Ok(FullyRefunded);
  } else if (isChargeSuccessful(charge)) {
    charge.captured ? Ok(Paid) : Ok(Authorized);
  } else {
    Error(NoPaymentStatus);
  };
