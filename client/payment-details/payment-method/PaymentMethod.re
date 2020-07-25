[@genType.import "@wordpress/i18n"]
external __: (string, string) => string = "__";

[@genType.import "@wordpress/i18n"]
external sprintf: (string, 'a, 'b) => string = "sprintf";

[@bs.val] external wcSettings: Js.Dict.t(string) = "wcSettings";

type paymentMethodDetailsFormat = {
  last4: string,
  fingerprint: string,
  date: string,
  cardType: string,
  id: string,
  name: option(string),
  email: option(string),
  country: string,
  cvcCheck: string,
  line1Check: option(string),
  postalCodeCheck: option(string),
  formattedAddress: option(string),
};

[@genType]
let formatPaymentMethodDetails = (charge: Charge.t) => {
  charge |> Js.log;
  let billingDetails = charge.billing_details;
  let payment_method = charge.payment_method;
  let card =
    charge.payment_method_details.card
    ->Belt.Option.getWithDefault(Card.make());

  let date =
    card.exp_month->string_of_int ++ " / " ++ card.exp_year->string_of_int;

  let fundingTranslated =
    switch (card.funding) {
    | "credit" => __("credit", "woocommerce-payments")
    | "debit" => __("debit", "woocommerce-payments")
    | "prepaid" => __("prepaid", "woocommerce-payments")
    | "unknown" => __("unknown", "woocommerce-payments")
    | _ => ""
    };
  let cardType =
    sprintf(
      __("%1$s %2$s card", "woocommerce-payments"),
      card.network |> String.capitalize_ascii,
      fundingTranslated,
    );

  let country =
    wcSettings->Js.Dict.get(card.country)->Belt.Option.getWithDefault("");

  {
    last4: card.last4,
    fingerprint: card.fingerprint,
    date,
    cardType,
    id: payment_method,
    name: billingDetails.name,
    email: billingDetails.email,
    country,
    cvcCheck: card.checks.cvc_check,
    line1Check: card.checks.address_line1_check,
    postalCodeCheck: card.checks.address_postal_code_check,
    formattedAddress: billingDetails.formatted_address,
  };
};
