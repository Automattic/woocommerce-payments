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
  type t = {status: DisputeStatus.t};

  let make = (~status=DisputeStatus.NotDisputed, ()) => {status: status};
};

module OutcomeType = {
  type t =
    | Authorized
    | ManualReview
    | IssuerDeclined
    | Blocked
    | Invalid;
};

module Outcome = {
  type t = {
    [@bs.as "type"]
    type_: OutcomeType.t,
    risk_level: string,
  };

  let make = (~type_=OutcomeType.Authorized, ~risk_level="", ()) => {
    type_,
    risk_level,
  };
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
