[@genType.import "@wordpress/i18n"]
external __: (string, string) => string = "__";

[@genType.import "@wordpress/i18n"]
external sprintf: (string, 'a, 'b) => string = "sprintf";

module WooCard = {
  [@bs.module "@woocommerce/components"] [@react.component]
  external make:
    (~title: React.element, ~children: React.element=?) => React.element =
    "Card";
};

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

[@genType]
[@react.component]
let make = (~charge: Charge.t, ~isLoading=false) => {
  let details = charge->formatPaymentMethodDetails;

  <WooCard
    title={
      <Loadable
        isLoading
        value={__("Payment method", "woocommerce-payments")->React.string}
      />
    }>
    <div className="payment-method-details">
      <div className="payment-method-details__column">
        <PaymentMethodDetail
          isLoading
          label={__("Number", "woocommerce-payments")->React.string}>
          {js| ••••\u0020 |js}->React.string
          details.last4->React.string
        </PaymentMethodDetail>
        <PaymentMethodDetail
          isLoading
          label={__("Fingerprint", "woocommerce-payments")->React.string}>
          details.fingerprint->React.string
        </PaymentMethodDetail>
        <PaymentMethodDetail
          isLoading
          label={__("Expires", "woocommerce-payments")->React.string}>
          details.date->React.string
        </PaymentMethodDetail>
        <PaymentMethodDetail
          isLoading label={__("Type", "woocommerce-payments")->React.string}>
          details.cardType->React.string
        </PaymentMethodDetail>
        <PaymentMethodDetail
          isLoading label={__("ID", "woocommerce-payments")->React.string}>
          details.id->React.string
        </PaymentMethodDetail>
      </div>
      <div className="payment-method-details__column">
        <PaymentMethodDetail
          isLoading label={__("Owner", "woocommerce-payments")->React.string}>
          {details.name->Belt.Option.getWithDefault("")->React.string}
        </PaymentMethodDetail>
        <PaymentMethodDetail
          isLoading
          label={__("Owner email", "woocommerce-payments")->React.string}>
          {details.email->Belt.Option.getWithDefault("")->React.string}
        </PaymentMethodDetail>
        <PaymentMethodDetail
          isLoading
          label={__("Address", "woocommerce-payments")->React.string}>
          <span
            dangerouslySetInnerHTML={
              "__html":
                details.formattedAddress->Belt.Option.getWithDefault(""),
            }
          />
        </PaymentMethodDetail>
        <PaymentMethodDetail
          isLoading
          label={__("Origin", "woocommerce-payments")->React.string}>
          details.country->React.string
        </PaymentMethodDetail>
        <PaymentMethodDetail
          isLoading
          label={__("CVC check", "woocommerce-payments")->React.string}>
          <PaymentMethodCheck
            checked={
              switch (details.cvcCheck) {
              | "pass" => PaymentMethodCheck.Passed
              | "fail" => Failed
              | "unavailable" => Unavailable
              | _ => NotChecked
              }
            }
          />
        </PaymentMethodDetail>
        <PaymentMethodDetail
          isLoading
          label={__("Street check", "woocommerce-payments")->React.string}>
          <PaymentMethodCheck
            checked={
              switch (details.line1Check) {
              | Some("pass") => PaymentMethodCheck.Passed
              | Some("fail") => Failed
              | Some("unavailable") => Unavailable
              | _ => NotChecked
              }
            }
          />
        </PaymentMethodDetail>
        <PaymentMethodDetail
          isLoading
          label={__("Zip check", "woocommerce-payments")->React.string}>
          <PaymentMethodCheck
            checked={
              switch (details.postalCodeCheck) {
              | Some("pass") => PaymentMethodCheck.Passed
              | Some("fail") => Failed
              | Some("unavailable") => Unavailable
              | _ => NotChecked
              }
            }
          />
        </PaymentMethodDetail>
      </div>
    </div>
  </WooCard>;
};

[@genType]
let default = make;
