/* Mocked charges for testing. */
let paidCharge: Types.Charge.charge = {
  amount: 0,
  amount_refunded: 0,
  status: "succeeded",
  paid: true,
  captured: true,
  id: "",
  object_: "",
  application: "",
  application_fee: "",
  application_fee_amount: 0,
  balance_transaction: "",
  billing_details: {
    address: {
      line1: "",
      line2: None,
      postal_code: "",
      city: "",
      country: "",
      state: "",
    },
    formatted_address: "",
    email: "",
    phone: "",
    name: "",
  },
  calculated_statement_descriptor: "",
  created: 0,
  currency: "",
  dispute: None,
  disputed: false,
  livemode: false,
  outcome: None,
  payment_intent: "",
  payment_method: "",
  receipt_email: "",
  receipt_number: "",
  receipt_url: "",
  refunded: false,
  level3: None,
  payment_method_details: None,
  refunds: None,
};
let failedCharge: Types.Charge.charge = {
  status: "failed",
  paid: false,
  captured: false,
  outcome: Some({
    type_: "issuer_declined",
  }),
  id: "",
  object_: "",
  application: "",
  application_fee: "",
  application_fee_amount: 0,
  balance_transaction: "",
  billing_details: {
    address: {
      line1: "",
      line2: None,
      postal_code: "",
      city: "",
      country: "",
      state: "",
    },
    formatted_address: "",
    email: "",
    phone: "",
    name: "",
  },
  calculated_statement_descriptor: "",
  created: 0,
  currency: "",
  dispute: None,
  disputed: false,
  livemode: false,
  payment_intent: "",
  payment_method: "",
  receipt_email: "",
  receipt_number: "",
  receipt_url: "",
  refunded: false,
  level3: None,
  payment_method_details: None,
  refunds: None,
  amount_refunded: 0,
  amount: 0,
};
let blockedCharge: Types.Charge.charge = {
  status: "failed",
  paid: false,
  captured: false,
  outcome: Some({
    type_: "blocked",
  }),
  id: "",
  object_: "",
  application: "",
  application_fee: "",
  application_fee_amount: 0,
  balance_transaction: "",
  billing_details: {
    address: {
      line1: "",
      line2: None,
      postal_code: "",
      city: "",
      country: "",
      state: "",
    },
    formatted_address: "",
    email: "",
    phone: "",
    name: "",
  },
  calculated_statement_descriptor: "",
  created: 0,
  currency: "",
  dispute: None,
  disputed: false,
  livemode: false,
  payment_intent: "",
  payment_method: "",
  receipt_email: "",
  receipt_number: "",
  receipt_url: "",
  refunded: false,
  level3: None,
  payment_method_details: None,
  refunds: None,
  amount: 0,
  amount_refunded: 0,
};
let authorizedCharge: Types.Charge.charge = {
  status: "succeeded",
  paid: true,
  captured: false,
  id: "",
  object_: "",
  application: "",
  application_fee: "",
  application_fee_amount: 0,
  balance_transaction: "",
  billing_details: {
    address: {
      line1: "",
      line2: None,
      postal_code: "",
      city: "",
      country: "",
      state: "",
    },
    formatted_address: "",
    email: "",
    phone: "",
    name: "",
  },
  calculated_statement_descriptor: "",
  created: 0,
  currency: "",
  dispute: None,
  disputed: false,
  livemode: false,
  outcome: None,
  payment_intent: "",
  payment_method: "",
  receipt_email: "",
  receipt_number: "",
  receipt_url: "",
  refunded: false,
  level3: None,
  payment_method_details: None,
  refunds: None,
  amount: 0,
  amount_refunded: 0,
};
let disputedChargeNeedsResponse: Types.Charge.charge = {
  disputed: true,
  dispute: Some({
    status: "needs_response",
  }),
  id: "",
  object_: "",
  application: "",
  application_fee: "",
  application_fee_amount: 0,
  balance_transaction: "",
  billing_details: {
    address: {
      line1: "",
      line2: None,
      postal_code: "",
      city: "",
      country: "",
      state: "",
    },
    formatted_address: "",
    email: "",
    phone: "",
    name: "",
  },
  calculated_statement_descriptor: "",
  created: 0,
  currency: "",
  livemode: false,
  outcome: None,
  payment_intent: "",
  payment_method: "",
  receipt_email: "",
  receipt_number: "",
  receipt_url: "",
  refunded: false,
  level3: None,
  payment_method_details: None,
  refunds: None,
  amount: 0,
  amount_refunded: 0,
  paid: false,
  captured: true,
  status: "",
};
let disputedChargeUnderReview: Types.Charge.charge = {
  disputed: true,
  dispute: Some({
    status: "under_review",
  }),
  id: "",
  object_: "",
  application: "",
  application_fee: "",
  application_fee_amount: 0,
  balance_transaction: "",
  billing_details: {
    address: {
      line1: "",
      line2: None,
      postal_code: "",
      city: "",
      country: "",
      state: "",
    },
    formatted_address: "",
    email: "",
    phone: "",
    name: "",
  },
  calculated_statement_descriptor: "",
  created: 0,
  currency: "",
  livemode: false,
  outcome: None,
  payment_intent: "",
  payment_method: "",
  receipt_email: "",
  receipt_number: "",
  receipt_url: "",
  refunded: false,
  level3: None,
  payment_method_details: None,
  refunds: None,
  amount: 0,
  amount_refunded: 0,
  paid: false,
  captured: true,
  status: "",
};
let disputedChargeWon: Types.Charge.charge = {
  disputed: true,
  dispute: Some({
    status: "won",
  }),
  id: "",
  object_: "",
  application: "",
  application_fee: "",
  application_fee_amount: 0,
  balance_transaction: "",
  billing_details: {
    address: {
      line1: "",
      line2: None,
      postal_code: "",
      city: "",
      country: "",
      state: "",
    },
    formatted_address: "",
    email: "",
    phone: "",
    name: "",
  },
  calculated_statement_descriptor: "",
  created: 0,
  currency: "",
  livemode: false,
  outcome: None,
  payment_intent: "",
  payment_method: "",
  receipt_email: "",
  receipt_number: "",
  receipt_url: "",
  refunded: false,
  level3: None,
  payment_method_details: None,
  refunds: None,
  amount: 0,
  amount_refunded: 0,
  paid: false,
  captured: true,
  status: "",
};
let disputedChargeLost: Types.Charge.charge = {
  disputed: true,
  dispute: Some({
    status: "lost",
  }),
  id: "",
  object_: "",
  application: "",
  application_fee: "",
  application_fee_amount: 0,
  balance_transaction: "",
  billing_details: {
    address: {
      line1: "",
      line2: None,
      postal_code: "",
      city: "",
      country: "",
      state: "",
    },
    formatted_address: "",
    email: "",
    phone: "",
    name: "",
  },
  calculated_statement_descriptor: "",
  created: 0,
  currency: "",
  livemode: false,
  outcome: None,
  payment_intent: "",
  payment_method: "",
  receipt_email: "",
  receipt_number: "",
  receipt_url: "",
  refunded: false,
  level3: None,
  payment_method_details: None,
  refunds: None,
  amount: 0,
  amount_refunded: 0,
  paid: false,
  captured: true,
  status: "",
};
let fullyRefundedCharge: Types.Charge.charge = {
  amount: 1500,
  refunded: true,
  amount_refunded: 1500,
  id: "",
  object_: "",
  application: "",
  application_fee: "",
  application_fee_amount: 0,
  balance_transaction: "",
  billing_details: {
    address: {
      line1: "",
      line2: None,
      postal_code: "",
      city: "",
      country: "",
      state: "",
    },
    formatted_address: "",
    email: "",
    phone: "",
    name: "",
  },
  calculated_statement_descriptor: "",
  created: 0,
  currency: "",
  dispute: None,
  disputed: false,
  livemode: false,
  outcome: None,
  payment_intent: "",
  payment_method: "",
  receipt_email: "",
  receipt_number: "",
  receipt_url: "",
  level3: None,
  payment_method_details: None,
  refunds: None,
  paid: true,
  captured: true,
  status: "",
};
let partiallyRefundedCharge: Types.Charge.charge = {
  amount: 1500,
  refunded: false,
  amount_refunded: 1200,
  id: "",
  object_: "",
  application: "",
  application_fee: "",
  application_fee_amount: 0,
  balance_transaction: "",
  billing_details: {
    address: {
      line1: "",
      line2: None,
      postal_code: "",
      city: "",
      country: "",
      state: "",
    },
    formatted_address: "",
    email: "",
    phone: "",
    name: "",
  },
  calculated_statement_descriptor: "",
  created: 0,
  currency: "",
  dispute: None,
  disputed: false,
  livemode: false,
  outcome: None,
  payment_intent: "",
  payment_method: "",
  receipt_email: "",
  receipt_number: "",
  receipt_url: "",
  level3: None,
  payment_method_details: None,
  refunds: None,
  paid: true,
  captured: true,
  status: "",
};

open Jest;

describe("Charge utilities (ReasonML)", () => {
  open Expect;

	test( "should identify a captured successful charge as successful", () => {
		paidCharge |> Util.isChargeSuccessful |> expect |> toEqual(true)
	} );

	test( "should identify a not captured successful charge as successful", () => {
		authorizedCharge |> Util.isChargeSuccessful |> expect |> toEqual(true)
	} );

	test( "should not identify a failed charge as successful", () => {
		failedCharge |> Util.isChargeSuccessful |> expect |> toEqual(false)
	} );

	test( "should not identify a blocked charge as successful", () => {
		blockedCharge |> Util.isChargeSuccessful |> expect |> toEqual(false)
	} );

	test( "should identify a failed charge as failed", () => {
		failedCharge |> Util.isChargeFailed |> expect |> toEqual(true)
	} );

	test( "should identify a blocked charge as blocked", () => {
		blockedCharge |> Util.isChargeBlocked |> expect |> toEqual(true)
	} );

	test( "should not identify a successful charge as failed", () => {
		paidCharge |> Util.isChargeFailed |> expect |> toEqual(false)
	} );

	test( "should not identify a successful charge as failed", () => {
		paidCharge |> Util.isChargeBlocked |> expect |> toEqual(false)
	} );

	test( "should identify a disputed charge as disputed", () => {
		disputedChargeWon |> Util.isChargeDisputed |> expect |> toEqual(true)
	} );

	test( "should identify a fully refunded charge as fully refunded", () => {
		fullyRefundedCharge |> Util.isChargeFullyRefunded |> expect |> toEqual(true)
	} );

	test( "should not identify a partially refunded charge as fully refunded", () => {
		partiallyRefundedCharge |> Util.isChargeFullyRefunded |> expect |> toEqual(false)
	} );

	test( "should not identify a successful charge as fully refunded", () => {
		paidCharge |> Util.isChargeFullyRefunded |> expect |> toEqual(false)
	} );

	test( "should identify a partially refunded charge as partially refunded", () => {
		partiallyRefundedCharge |> Util.isChargePartiallyRefunded |> expect |> toEqual(true)
	} );

	test( "should not identify a fully refunded charge as partilly refunded", () => {
		fullyRefundedCharge |> Util.isChargePartiallyRefunded |> expect |> toEqual(false)
	} );

	test( "should not identify a successful charge as partilly refunded", () => {
		paidCharge |> Util.isChargePartiallyRefunded |> expect |> toEqual(false)
	} );

	test( "should return status paid for captured successful charges", () => {
		paidCharge |> Util.getChargeStatus |> expect |> toEqual(Ok(Util.Paid))
	} );

	test( "should return status authorized for not captured successful charges", () => {
		authorizedCharge |> Util.getChargeStatus |> expect |> toEqual(Ok(Util.Authorized))
	} );

	test( "should return status failed for failed charges", () => {
		failedCharge |> Util.getChargeStatus |> expect |> toEqual(Ok(Util.Failed))
	} );

	test( "should return status disputed_needs_response for disputed charges that needs response", () => {
		disputedChargeNeedsResponse |> Util.getChargeStatus |> expect |> toEqual(Ok(Util.DisputeNeedsResponse))
	} );

	test( "should return status disputed_under_review for disputed charges in review", () => {
		disputedChargeUnderReview |> Util.getChargeStatus |> expect |> toEqual(Ok(Util.DisputeUnderReview))
	} );

	test( "should return status disputed_won for won disputed charges", () => {
		disputedChargeWon |> Util.getChargeStatus |> expect |> toEqual(Ok(Util.DisputeWon))
	} );

	test( "should return status disputed_lost for lost disputed charges", () => {
		disputedChargeLost |> Util.getChargeStatus |> expect |> toEqual(Ok(Util.DisputeLost))
	} );

	test( "should return status refunded_full for fully refunded charges", () => {
		fullyRefundedCharge |> Util.getChargeStatus |> expect |> toEqual(Ok(Util.FullyRefunded))
	} );

	test( "should return status refunded_partial for partially refunded charges", () => {
		partiallyRefundedCharge |> Util.getChargeStatus |> expect |> toEqual(Ok(Util.PartiallyRefunded))
	} );
});
