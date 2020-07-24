module Address = {
  type t = {
    city: option(string),
    country: option(string),
    line1: option(string),
    line2: option(string),
    postal_code: option(string),
    state: option(string),
  };

  let make =
      (
        ~city=None,
        ~country=None,
        ~line1=None,
        ~line2=None,
        ~postal_code=None,
        ~state=None,
        (),
      ) => {
    city,
    country,
    line1,
    line2,
    postal_code,
    state,
  };
};

module BillingDetails = {
  type t = {
    address: Address.t,
    email: option(string),
    name: option(string),
    phone: option(string),
    formatted_address: option(string),
  };

  let make =
      (
        ~address=Address.make(),
        ~email=None,
        ~name=None,
        ~phone=None,
        ~formatted_address=None,
        (),
      ) => {
    address,
    email,
    name,
    phone,
    formatted_address,
  };
};

module Order = {
  type t = {
    url: string,
    number: int,
  };

  let make = (~url="", ~number=0, ()) => {url, number};
};

module Level3LineItem = {
  type t = {
    discount_amount: int,
    product_code: string,
    product_description: string,
    quantity: int,
    tax_amount: int,
    unit_cost: int,
  };

  let make =
      (
        ~discount_amount=0,
        ~product_code="",
        ~product_description="",
        ~quantity=0,
        ~tax_amount=0,
        ~unit_cost=0,
        (),
      ) => {
    discount_amount,
    product_code,
    product_description,
    quantity,
    tax_amount,
    unit_cost,
  };
};

module Level3 = {
  type t = {
    line_items: array(Level3LineItem.t),
    merchant_reference: string,
    shipping_address_zip: string,
    shipping_amount: int,
    shipping_from_zip: string,
  };

  let make =
      (
        ~line_items=[||],
        ~merchant_reference="",
        ~shipping_address_zip="",
        ~shipping_amount=0,
        ~shipping_from_zip="",
        (),
      ) => {
    line_items,
    merchant_reference,
    shipping_address_zip,
    shipping_amount,
    shipping_from_zip,
  };
};

module Dispute = {
  type t = {status: string};

  let make = (~status="", ()) => {status: status};
};

module Outcome = {
  type t = {
    [@bs.as "type"]
    type_: string,
    risk_level: string,
  };

  let make = (~type_="", ~risk_level="", ()) => {type_, risk_level};
};

module Refund = {
  type t;
};

module Refunds = {
  type t = {
    [@bs.as "object"]
    object_: string,
    data: array(Refund.t),
    has_more: bool,
    total_count: int,
    url: string,
  };

  let make =
      (~object_="", ~data=[||], ~has_more=false, ~total_count=0, ~url="", ()) => {
    object_,
    data,
    has_more,
    total_count,
    url,
  };
};

module Card = {
  type checks = {
    address_line1_check: option(string),
    address_postal_code_check: option(string),
    cvc_check: string,
  };
  type t = {
    checks,
    country: string,
    exp_month: int,
    exp_year: int,
    fingerprint: string,
    funding: string,
    last4: string,
    network: string,
  };

  let make_checks =
      (
        ~address_line1_check=None,
        ~address_postal_code_check=None,
        ~cvc_check="",
        (),
      ) => {
    address_line1_check,
    address_postal_code_check,
    cvc_check,
  };

  let make =
      (
        ~checks=make_checks(),
        ~country="",
        ~exp_month=0,
        ~exp_year=0,
        ~fingerprint="",
        ~funding="",
        ~last4="",
        ~network="",
        (),
      ) => {
    checks,
    country,
    exp_month,
    exp_year,
    fingerprint,
    funding,
    last4,
    network,
  };
};

module PaymentMethodDetails = {
  type t = {
    card: Card.t,
    [@bs.as "type"]
    type_: string,
  };

  let make = (~card=Card.make(), ~type_="", ()): t => {card, type_};
};

module Charge = {
  module Metadata = {
    type t;
  };

  type t = {
    id: string,
    [@bs.as "object"]
    object_: string,
    amount: int,
    amount_refunded: int,
    application: option(string),
    application_fee: option(string),
    application_fee_amount: option(int),
    balance_transaction: string,
    billing_details: BillingDetails.t,
    calculated_statement_descriptor: option(string),
    captured: bool,
    created: int,
    currency: string,
    dispute: option(Dispute.t),
    disputed: bool,
    level3: option(Level3.t),
    livemode: bool,
    outcome: option(Outcome.t),
    paid: bool,
    payment_intent: option(string),
    payment_method: string,
    payment_method_details: PaymentMethodDetails.t,
    receipt_email: option(string),
    receipt_number: option(string),
    receipt_url: string,
    refunded: bool,
    refunds: option(Refunds.t),
    status: string,
  };

  let make =
      (
        ~id="",
        ~object_="",
        ~amount=0,
        ~amount_refunded=0,
        ~application=None,
        ~application_fee=None,
        ~application_fee_amount=None,
        ~balance_transaction="",
        ~billing_details=BillingDetails.make(),
        ~calculated_statement_descriptor=None,
        ~captured=false,
        ~created=0,
        ~currency="",
        ~dispute=None,
        ~disputed=false,
        ~level3=None,
        ~livemode=false,
        ~outcome=None,
        ~paid=false,
        ~payment_intent=None,
        ~payment_method="",
        ~payment_method_details=PaymentMethodDetails.make(),
        ~receipt_email=None,
        ~receipt_number=None,
        ~receipt_url="",
        ~refunded=false,
        ~refunds=None,
        ~status="",
        (),
      ) => {
    id,
    object_,
    amount,
    amount_refunded,
    application,
    application_fee,
    application_fee_amount,
    balance_transaction,
    billing_details,
    calculated_statement_descriptor,
    captured,
    created,
    currency,
    dispute,
    disputed,
    level3,
    livemode,
    outcome,
    paid,
    payment_intent,
    payment_method,
    payment_method_details,
    receipt_email,
    receipt_number,
    receipt_url,
    refunded,
    refunds,
    status,
  };

  module RequestError = {
    type t = {
      code: string,
      message: string,
      data: string,
    };
  };

  module Request = {
    type t = {
      charge: option(t),
      chargeError: option(RequestError.t),
      isLoading: bool,
    };
  };
};

module ChargeReducer = {
  module Event = {
    type t = {
      [@bs.as "type"]
      type_: string,
      id: string,
      data: option(Charge.t),
      error: option(Charge.RequestError.t),
    };
  };

  type chargeStateEntry = {
    data: option(Charge.t),
    error: option(Charge.RequestError.t),
  };

  module State = {
    type t = Belt.Map.String.t(chargeStateEntry);
  };
};

module Reducer = {
  type event = ChargeReducer.Event.t;

  type state = {charges: ChargeReducer.State.t};
};
