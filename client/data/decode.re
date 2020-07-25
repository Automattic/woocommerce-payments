let address: 'a => Types.Address.t =
  json =>
    Json.Decode.{
      city: json |> optional(field("city", string)),
      country: json |> optional(field("country", string)),
      line1: json |> optional(field("line1", string)),
      line2: json |> optional(field("line2", string)),
      postal_code: json |> optional(field("postal_code", string)),
      state: json |> optional(field("state", string)),
    };

let billingDetails: 'a => Types.BillingDetails.t =
  json =>
    Json.Decode.{
      address: json |> field("address", address),
      email: json |> optional(field("email", string)),
      name: json |> optional(field("email", string)),
      phone: json |> optional(field("phone", string)),
      formatted_address:
        json |> optional(field("formatted_address", string)),
    };

let dispute: 'a => Types.Dispute.t =
  json =>
    Json.Decode.{
      status: json |> field("status", string) |> Util.getDisputeStatus,
    };

let level3LineItem: 'a => Types.Level3LineItem.t =
  json =>
    Json.Decode.{
      discount_amount: json |> field("discount_amount", int),
      product_code: json |> field("product_code", string),
      product_description: json |> field("product_description", string),
      quantity: json |> field("quantity", int),
      tax_amount: json |> field("tax_amount", int),
      unit_cost: json |> field("unit_cost", int),
    };

let level3: 'a => Types.Level3.t =
  json =>
    Json.Decode.{
      line_items: json |> field("discount_amount", array(level3LineItem)),
      merchant_reference: json |> field("merchant_reference", string),
      shipping_address_zip: json |> field("shipping_address_zip", string),
      shipping_amount: json |> field("shipping_amount", int),
      shipping_from_zip: json |> field("shipping_from_zip", string),
    };

let outcome: 'a => Types.Outcome.t =
  json =>
    Json.Decode.{
      type_: json |> field("type", string) |> Util.getOutcomeType,
      risk_level: json |> field("risk_level", string),
    };

let refunds: 'a => Types.Refunds.t =
  json =>
    Json.Decode.{
      object_: json |> field("object", string),
      data: [||],
      has_more: json |> field("has_more", bool),
      total_count: json |> field("total_count", int),
      url: json |> field("url", string),
    };

let checks: 'a => Types.Card.checks =
  json =>
    Json.Decode.{
      address_line1_check:
        json |> optional(field("address_line1_check", string)),
      address_postal_code_check:
        json |> optional(field("address_postal_code_check", string)),
      cvc_check: json |> field("cvc_check", string),
    };

let card: 'a => Types.Card.t =
  json =>
    Json.Decode.{
      checks: json |> field("checks", checks),
      country: json |> field("country", string),
      exp_month: json |> field("exp_month", int),
      exp_year: json |> field("exp_year", int),
      fingerprint: json |> field("fingerprint", string),
      funding: json |> field("funding", string),
      last4: json |> field("last4", string),
      network: json |> field("network", string),
    };

let paymentMethodDetails: 'a => Types.PaymentMethodDetails.t =
  json =>
    Json.Decode.{
      card: json |> field("card", card),
      type_: json |> field("type", string),
    };

let charge: 'a => Charge.t =
  json =>
    Json.Decode.{
      id: json |> field("id", string),
      object_: json |> field("object", string),
      amount: json |> field("amount", int),
      amount_refunded: json |> field("amount_refunded", int),
      application: json |> optional(field("application", string)),
      application_fee: json |> optional(field("application_fee", string)),
      application_fee_amount:
        json |> optional(field("application_fee_amount", int)),
      balance_transaction: json |> field("balance_transaction", string),
      billing_details: json |> field("billing_details", billingDetails),
      calculated_statement_descriptor:
        json |> optional(field("calculated_statement_descriptor", string)),
      captured: json |> field("captured", bool),
      created: json |> field("created", int),
      currency: json |> field("currency", string),
      dispute: json |> optional(field("dispute", dispute)),
      disputed: json |> field("disputed", bool),
      level3: json |> optional(field("level3", level3)),
      livemode: json |> field("livemode", bool),
      outcome: json |> optional(field("outcome", outcome)),
      paid: json |> field("paid", bool),
      payment_intent: json |> optional(field("payment_intent", string)),
      payment_method: json |> field("payment_method", string),
      payment_method_details:
        json |> field("payment_method_details", paymentMethodDetails),
      receipt_email: json |> optional(field("receipt_email", string)),
      receipt_number: json |> optional(field("receipt_number", string)),
      receipt_url: json |> field("receipt_url", string),
      refunded: json |> field("refunded", bool),
      refunds: json |> optional(field("refunds", refunds)),
      status: json |> field("status", string) |> Util.getChargeStatus,
    };
