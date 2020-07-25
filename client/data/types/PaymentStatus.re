[@genType]
type t =
  | Disputed(DisputeStatus.t)
  | Failed
  | Blocked
  | PartiallyRefunded
  | FullyRefunded
  | Paid
  | Authorized;

[@genType]
let fromCharge = (charge: Charge.t) => {
  let outcomeType = charge.outcome->Belt.Option.map(o => o.type_);
  let disputeStatus =
    charge.disputed ? charge.dispute->Belt.Option.map(d => d.status) : None;

  let fullyRefunded = charge.refunded;
  let partiallyRefunded = charge.amount_refunded > 0 && !fullyRefunded;

  switch (charge.status, outcomeType, disputeStatus) {
  | (Failed, Some(IssuerDeclined), _)
  | (Failed, Some(Invalid), _) => Failed
  | (Failed, Some(Blocked), _) => Blocked
  | (_, _, Some(d)) => Disputed(d)
  | _ =>
    switch (partiallyRefunded, fullyRefunded) {
    | (true, _) => PartiallyRefunded
    | (_, true) => FullyRefunded
    | _ => charge.captured ? Paid : Authorized
    }
  };
};
