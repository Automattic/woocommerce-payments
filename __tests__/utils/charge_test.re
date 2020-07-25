/* Mocked charges for testing. */
let paidCharge =
  Charge.make(~status=Succeeded, ~paid=true, ~captured=true, ());
let failedCharge =
  Charge.make(
    ~status=Failed,
    ~paid=false,
    ~captured=false,
    ~outcome=Types.Outcome.make(~type_=IssuerDeclined, ())->Some,
    (),
  );
let blockedCharge =
  Charge.make(
    ~status=Failed,
    ~paid=false,
    ~captured=false,
    ~outcome=Types.Outcome.make(~type_=Blocked, ())->Some,
    (),
  );
let authorizedCharge =
  Charge.make(~status=Succeeded, ~paid=true, ~captured=false, ());
let fullyRefundedCharge =
  Charge.make(~amount=1500, ~refunded=true, ~amount_refunded=1500, ());
let partiallyRefundedCharge =
  Charge.make(~amount=1500, ~refunded=false, ~amount_refunded=1200, ());

let getDisputedChargeWithStatus = status => {
  Charge.make(
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
      ("succeeded", ChargeStatus.Succeeded),
      ("failed", Failed),
      ("pending", Pending),
    ];

    testAll(
      "returns status for charge", chargeStatuses, ((statusString, status)) => {
      statusString |> Util.getChargeStatus |> expect |> toEqual(status)
    });

    let disputeStatuses = [
      ("needs_response", DisputeStatus.NeedsResponse),
      ("under_review", UnderReview),
      ("won", Won),
      ("lost", Lost),
      ("warning_needs_response", WarningNeedsResponse),
      ("warning_under_review", WarningUnderReview),
      ("warning_closed", WarningClosed),
      ("charge_refunded", ChargeRefunded),
    ];

    testAll(
      "returns disputed status", disputeStatuses, ((statusString, status)) => {
      statusString |> Util.getDisputeStatus |> expect |> toEqual(status)
    });
  });
});
