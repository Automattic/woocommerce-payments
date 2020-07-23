/* Mocked charges for testing. */
let paidCharge =
  Types.Charge.make(~status="succeeded", ~paid=true, ~captured=true, ());
let failedCharge =
  Types.Charge.make(
    ~status="failed",
    ~paid=false,
    ~captured=false,
    ~outcome=Types.Outcome.make(~type_="issuer_declined", ())->Some,
    (),
  );
let blockedCharge =
  Types.Charge.make(
    ~status="failed",
    ~paid=false,
    ~captured=false,
    ~outcome=Types.Outcome.make(~type_="blocked", ())->Some,
    (),
  );
let authorizedCharge =
  Types.Charge.make(~status="succeeded", ~paid=true, ~captured=false, ());
let fullyRefundedCharge =
  Types.Charge.make(~amount=1500, ~refunded=true, ~amount_refunded=1500, ());
let partiallyRefundedCharge =
  Types.Charge.make(~amount=1500, ~refunded=false, ~amount_refunded=1200, ());

let getDisputedChargeWithStatus = status => {
  Types.Charge.make(
    ~disputed=true,
    ~dispute=Types.Dispute.make(~status, ())->Some,
    (),
  );
};

open Jest;

describe("Charge utilities (ReasonML)", () => {
  open Expect;
  describe("isCharge methods", () => {
    test("should identify a captured successful charge as successful", () => {
      paidCharge |> Util.isChargeSuccessful |> expect |> toEqual(true)
    });

    test("should identify a not captured successful charge as successful", () => {
      authorizedCharge |> Util.isChargeSuccessful |> expect |> toEqual(true)
    });

    test("should not identify a failed charge as successful", () => {
      failedCharge |> Util.isChargeSuccessful |> expect |> toEqual(false)
    });

    test("should not identify a blocked charge as successful", () => {
      blockedCharge |> Util.isChargeSuccessful |> expect |> toEqual(false)
    });

    test("should identify a failed charge as failed", () => {
      failedCharge |> Util.isChargeFailed |> expect |> toEqual(true)
    });

    test("should identify a blocked charge as blocked", () => {
      blockedCharge |> Util.isChargeBlocked |> expect |> toEqual(true)
    });

    test("should not identify a successful charge as failed", () => {
      paidCharge |> Util.isChargeFailed |> expect |> toEqual(false)
    });

    test("should not identify a successful charge as failed", () => {
      paidCharge |> Util.isChargeBlocked |> expect |> toEqual(false)
    });

    test("should identify a fully refunded charge as fully refunded", () => {
      fullyRefundedCharge
      |> Util.isChargeFullyRefunded
      |> expect
      |> toEqual(true)
    });

    test(
      "should not identify a partially refunded charge as fully refunded", () => {
      partiallyRefundedCharge
      |> Util.isChargeFullyRefunded
      |> expect
      |> toEqual(false)
    });

    test("should not identify a successful charge as fully refunded", () => {
      paidCharge |> Util.isChargeFullyRefunded |> expect |> toEqual(false)
    });

    test(
      "should identify a partially refunded charge as partially refunded", () => {
      partiallyRefundedCharge
      |> Util.isChargePartiallyRefunded
      |> expect
      |> toEqual(true)
    });

    test(
      "should not identify a fully refunded charge as partially refunded", () => {
      fullyRefundedCharge
      |> Util.isChargePartiallyRefunded
      |> expect
      |> toEqual(false)
    });

    test("should not identify a successful charge as partially refunded", () => {
      paidCharge |> Util.isChargePartiallyRefunded |> expect |> toEqual(false)
    });
  });

  describe("getChargeStatus", () => {
    let chargeStatuses = [
      (Util.Paid, paidCharge),
      (Util.Authorized, authorizedCharge),
      (Util.Failed, failedCharge),
      (Util.FullyRefunded, fullyRefundedCharge),
      (Util.PartiallyRefunded, partiallyRefundedCharge),
    ];

    testAll("returns status for charge", chargeStatuses, ((status, charge)) => {
      charge |> Util.getChargeStatus |> expect |> toEqual(status->Ok)
    });

    let disputeStatuses = [
      ("needs_response", Util.DisputeNeedsResponse),
      ("under_review", Util.DisputeUnderReview),
      ("won", Util.DisputeWon),
      ("lost", Util.DisputeLost),
      ("warning_needs_response", Util.DisputeNeedsResponse),
      ("warning_under_review", Util.DisputeUnderReview),
      ("warning_closed", Util.Disputed),
    ];

    testAll(
      "returns disputed status", disputeStatuses, ((statusString, status)) => {
      statusString
      |> getDisputedChargeWithStatus
      |> Util.getChargeStatus
      |> expect
      |> toEqual(status->Ok)
    });

    testAll(
      "disputed statuses take precedence over refunds",
      disputeStatuses,
      ((statusString, status)) => {
        let charge =
          Types.Charge.make(
            ~amount=1500,
            ~refunded=true,
            ~amount_refunded=1500,
            ~disputed=true,
            ~dispute=Types.Dispute.make(~status=statusString, ())->Some,
            (),
          );
        charge |> Util.getChargeStatus |> expect |> toEqual(status->Ok);
      },
    );
  });
});
