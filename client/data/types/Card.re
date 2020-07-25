type checks = {
  address_line1_check: option(string),
  address_postal_code_check: option(string),
  cvc_check: string,
};

type t = {
  brand: string,
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
      ~brand="",
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
  brand,
  checks,
  country,
  exp_month,
  exp_year,
  fingerprint,
  funding,
  last4,
  network,
};
