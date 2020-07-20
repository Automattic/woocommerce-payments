module Charge = {
  type id = string;

  type order = {
    url: string,
    number: int,
  };

  type address = {
    city: string,
    country: string,
    line1: string,
    line2: option(string),
    postal_code: string,
    state: string,
  };

  type billingDetails = {
    address,
    email: string,
    name: string,
    phone: string,
    formatted_address: string,
  };

  type level3LineItem = {
    discount_amount: int,
    product_code: string,
    product_description: string,
    quantity: int,
    tax_amount: int,
    unit_cost: int,
  };

  type level3 = {
    line_items: array(level3LineItem),
    merchant_reference: string,
    shipping_address_zip: string,
    shipping_amount: int,
    shipping_from_zip: string,
  };

  type chargeMetadata;
  type chargeOutcome;
  type paymentMethodDetails;
  type refund;
  type refunds = {
    [@bs.as "object"]
    object_: string,
    data: array(refund),
    has_more: bool,
    total_count: int,
    url: string,
  };

  type charge = {
    id,
    [@bs.as "object"]
    object_: string,
    amount: int,
    amount_refunded: int,
    application: string,
    application_fee: string,
    application_fee_amount: int,
    balance_transaction: string,
    billing_details: billingDetails,
    calculated_statement_descriptor: string,
    captured: bool,
    created: int,
    currency: string,
    disputed: bool,
    level3,
    livemode: bool,
    payment_intent: string,
    payment_method: string,
    payment_method_details: paymentMethodDetails,
    receipt_email: string,
    receipt_number: string,
    receipt_url: string,
    refunded: bool,
    refunds,
    status: string,
  };

  type chargeError = {
    code: string,
    message: string,
    data: string,
  };

  type chargeRequest = {
    charge: option(charge),
    chargeError: option(chargeError),
    isLoading: bool,
  };

  module Reducer = {
    type event = {
      [@bs.as "type"]
      type_: string,
      id,
      data: option(charge),
      error: option(chargeError),
    };

    type chargeStateEntry = {
      data: option(charge),
      error: option(chargeError),
    };

    type state = Js.Dict.t(chargeStateEntry);
  };
};

module Reducer = {
  type event = Charge.Reducer.event;

  type state = {charges: Charge.Reducer.state};
};
