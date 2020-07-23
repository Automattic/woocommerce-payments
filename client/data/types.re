module Address = {
  type t = {
    city: string,
    country: string,
    line1: string,
    line2: option(string),
    postal_code: string,
    state: string,
  };

  let make =
      (
        ~city="",
        ~country="",
        ~line1="",
        ~line2=None,
        ~postal_code="",
        ~state="",
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
    email: string,
    name: string,
    phone: string,
    formatted_address: string,
  };

  let make =
      (
        ~address=Address.make(),
        ~email="",
        ~name="",
        ~phone="",
        ~formatted_address="",
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
  };

  let make = (~type_="", ()) => {type_: type_};
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

module PaymentMethodDetails = {
  type t;
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
    application: string,
    application_fee: string,
    application_fee_amount: int,
    balance_transaction: string,
    billing_details: BillingDetails.t,
    calculated_statement_descriptor: string,
    captured: bool,
    created: int,
    currency: string,
    dispute: option(Dispute.t),
    disputed: bool,
    level3: option(Level3.t),
    livemode: bool,
    outcome: option(Outcome.t),
    paid: bool,
    payment_intent: string,
    payment_method: string,
    payment_method_details: option(PaymentMethodDetails.t),
    receipt_email: string,
    receipt_number: string,
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
        ~application="",
        ~application_fee="",
        ~application_fee_amount=0,
        ~balance_transaction="",
        ~billing_details=BillingDetails.make(),
        ~calculated_statement_descriptor="",
        ~captured=false,
        ~created=0,
        ~currency="",
        ~dispute=None,
        ~disputed=false,
        ~level3=None,
        ~livemode=false,
        ~outcome=None,
        ~paid=false,
        ~payment_intent="",
        ~payment_method="",
        ~payment_method_details=None,
        ~receipt_email="",
        ~receipt_number="",
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
