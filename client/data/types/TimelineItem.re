type t = {
  date: Js.Date.t,
  icon: React.element,
  headline: React.element,
  body: array(React.element),
  hideTimestamp: bool,
};

let make =
    (
      ~date: Js.Date.t,
      ~icon: React.element,
      ~headline: React.element,
      ~body: array(React.element),
      ~hideTimestamp: bool,
      (),
    ) => {
  date,
  icon,
  headline,
  body,
  hideTimestamp,
};

module Util = {
  [@genType.import "@wordpress/i18n"]
  external __: (string, string) => string = "__";

  [@bs.module "@wordpress/i18n"]
  external sprintf1: (string, 'a) => string = "sprintf";
  [@bs.module "@wordpress/i18n"]
  external sprintf2: (string, 'a, 'b) => string = "sprintf";

  [@genType.import "wordpress-element"]
  external createInterpolateElement:
    (string, Js.Dict.t(React.element)) => React.element =
    "__experimentalCreateInterpolateElement";

  module QueryArgs = {
    type t = {
      page: string,
      path: string,
      id: string,
    };

    let make = (~page, ~path, ~id, ()) => {page, path, id};
  };
  [@genType.import "@wordpress/url"]
  external addQueryArgs: (string, QueryArgs.t) => string = "addQueryArgs";

  module Gridicon = {
    [@bs.module] [@react.component]
    external make: (~icon: string, ~className: string=?) => React.element =
      "gridicons";
  };

  module Currency = {
    type t;

    type currencyData = {
      code: string,
      position: string,
      grouping: string,
      decimal: string,
      precision: float,
    };

    [@bs.new] [@bs.module "@woocommerce/currency"]
    external createCurrencyFromCurrencyData: currencyData => t = "default";

    [@bs.new] [@bs.module "@woocommerce/currency"]
    external createCurrency: unit => t = "default";

    [@bs.module "@woocommerce/currency"]
    external getCurrencyData: unit => Js.Dict.t(currencyData) =
      "getCurrencyData";

    let getCurrency = (currencyCode: string) => {
      switch (Js.Dict.get(getCurrencyData(), currencyCode)) {
      | None => createCurrency()
      | Some(c) => createCurrencyFromCurrencyData(c)
      };
    };

    [@bs.send]
    external formatCurrency: (t, float) => string = "formatCurrency";
  };

  module Moment = {
    type t;
  };
  [@genType.import "@wordpress/date"]
  external dateI18n: (string, Moment.t) => string = "dateI18n";
  [@genType.import ("moment", "default")]
  external moment: float => Moment.t = "moment";

  module Lodash = {
    [@genType.import "lodash"]
    external flatMap: (array(array('a)), 'a => array('b)) => array('b) =
      "flatMap";
  };

  type disputeReasons = {
    display: string,
    overview: array(string),
    summary: array(string),
    required: array(string),
    respond: array(string),
  };
  [@genType.import "disputes/strings"]
  external disputeReasons: Js.Dict.t(disputeReasons) = "reasons";

  let makeStatusChangeTimelineItem = (event: TimelineEvent.t, status) => {
    {
      date: Js.Date.fromFloat(event.datetime *. 1000.),
      icon: <Gridicon icon="sync" />,
      headline:
        sprintf1(
          // translators: %s new status, for example Authorized, Refunded, etc
          __("Payment status changed to %s", "woocommerce-payments"),
          status,
        )
        ->React.string,
      body: [||],
      hideTimestamp: true,
    };
  };

  let makeDepositTimelineItem =
      (event: TimelineEvent.t, ~body=[||], ~formattedAmount, ~isPositive, ()) => {
    let headline =
      switch (event.deposit->Js.Nullable.toOption) {
      | None =>
        sprintf1(
          isPositive
            // translators: %s - formatted amount
            ? __(
                "%s will be added to a future deposit",
                "woocommerce-payments",
              )  // translators: %s - formatted amount
            : __(
                "%s will be deducted from a future deposit",
                "woocommerce-payments",
              ),
          formattedAmount,
        )
        ->React.string
      | Some(d) =>
        let headlineText =
          sprintf2(
            isPositive
              // translators: %1$s - formatted amount, %2$s - deposit arrival date, <a> - link to the deposit
              ? __(
                  "%1$s was added to your <a>%2$s deposit</a>",
                  "woocommerce-payments",
                )  // translators: %1$s - formatted amount, %2$s - deposit arrival date, <a> - link to the deposit
              : __(
                  "%1$s was deducted from your <a>%2$s deposit</a>",
                  "woocommerce-payments",
                ),
            formattedAmount,
            dateI18n("M j, Y", moment(d.arrival_date *. 1000.)),
          );

        let depositUrl =
          addQueryArgs(
            "admin.php",
            QueryArgs.make(
              ~page="wc-admin",
              ~path="/payments/deposits/details",
              ~id=d.id,
              (),
            ),
          );

        createInterpolateElement(
          headlineText,
          Js.Dict.fromList([("a", <a href=depositUrl />)]),
        );
      };

    {
      date: Js.Date.fromFloat(event.datetime *. 1000.),
      icon: <Gridicon icon={isPositive ? "plus" : "minus"} />,
      headline,
      body,
      hideTimestamp: true,
    };
  };

  let makeMainTimelineItem =
      (event: TimelineEvent.t, ~headline, ~icon, ~iconClass, ~body=[||], ()) => {
    date: Js.Date.fromFloat(event.datetime *. 1000.),
    headline,
    icon: <Gridicon icon className=iconClass />,
    body,
    hideTimestamp: false,
  };

  let mapEventToTimelineItems = event => {
    let {type_}: TimelineEvent.t = event;
    let currency =
      Currency.getCurrency(event.currency === "" ? "USD" : event.currency);

    let formatCurrency = amount =>
      Js.Math.abs_float(amount->float_of_int /. 100.)
      |> Currency.formatCurrency(currency);

    let stringWithAmount = (headline, amount) =>
      sprintf1(headline, amount->formatCurrency);

    switch (type_) {
    | "authorized" => [|
        event->makeMainTimelineItem(
          ~headline=
            __(
              "A payment of %s was successfully authorized",
              "woocommerce-payments",
            )
            ->stringWithAmount(
                event.amount
                ->Js.Nullable.toOption
                ->Belt.Option.getWithDefault(0),
              )
            ->React.string,
          ~icon="checkmark",
          ~iconClass="is-warning",
          (),
        ),
        event->makeStatusChangeTimelineItem(
          __("Authorized", "woocommerce-payments"),
        ),
      |]
    | "authorization_voided" => [|
        event->makeMainTimelineItem(
          ~headline=
            __("Authorization for %s was voided", "woocommerce-payments")
            ->stringWithAmount(
                event.amount
                ->Js.Nullable.toOption
                ->Belt.Option.getWithDefault(0),
              )
            ->React.string,
          ~icon="checkmark",
          ~iconClass="is-warning",
          (),
        ),
        event->makeStatusChangeTimelineItem(
          __("Authorization Voided", "woocommerce-payments"),
        ),
      |]
    | "authorization_expired" => [|
        event->makeMainTimelineItem(
          ~headline=
            __("Authorization for %s expired", "woocommerce-payments")
            ->stringWithAmount(
                event.amount
                ->Js.Nullable.toOption
                ->Belt.Option.getWithDefault(0),
              )
            ->React.string,
          ~icon="cross",
          ~iconClass="is-error",
          (),
        ),
        event->makeStatusChangeTimelineItem(
          __("Authorization Expired", "woocommerce-payments"),
        ),
      |]
    | "captured" =>
      let formattedNet =
        formatCurrency(
          event.amount->Js.Nullable.toOption->Belt.Option.getWithDefault(0)
          - event.fee,
        );
      [|
        event->makeMainTimelineItem(
          ~headline=
            __(
              "A payment of %s was successfully charged",
              "woocommerce-payments",
            )
            ->stringWithAmount(
                event.amount
                ->Js.Nullable.toOption
                ->Belt.Option.getWithDefault(0),
              )
            ->React.string,
          ~icon="checkmark",
          ~iconClass="is-success",
          ~body=[|
            __("Fee: %s", "woocommerce-payments")
            ->stringWithAmount(event.fee)
            ->React.string,
            __("Net deposit: %s", "woocommerce-payments")
            ->sprintf1(formattedNet)
            ->React.string,
          |],
          (),
        ),
        event->makeDepositTimelineItem(
          ~formattedAmount=formattedNet,
          ~isPositive=true,
          (),
        ),
        event->makeStatusChangeTimelineItem(
          __("Paid", "woocommerce-payments"),
        ),
      |];
    | "partial_refund"
    | "full_refund" =>
      let formattedAmount = formatCurrency(event.amount_refunded);
      [|
        event->makeMainTimelineItem(
          ~headline=
            __(
              "A payment of %s was successfully refunded",
              "woocommerce-payments",
            )
            ->sprintf1(formattedAmount)
            ->React.string,
          ~icon="checkmark",
          ~iconClass="is-success",
          (),
        ),
        event->makeDepositTimelineItem(
          ~formattedAmount,
          ~isPositive=false,
          (),
        ),
        event->makeStatusChangeTimelineItem(
          "full_refund" === type_
            ? __("Refunded", "woocommerce-payments")
            : __("Partial Refund", "woocommerce-payments"),
        ),
      |];
    | "failed" => [|
        event->makeMainTimelineItem(
          ~headline=
            __("A payment of %s failed", "woocommerce-payments")
            ->stringWithAmount(
                event.amount
                ->Js.Nullable.toOption
                ->Belt.Option.getWithDefault(0),
              )
            ->React.string,
          ~icon="cross",
          ~iconClass="is-error",
          (),
        ),
        event->makeStatusChangeTimelineItem(
          __("Failed", "woocommerce-payments"),
        ),
      |]
    | "dispute_needs_response" =>
      let reasonHeadline =
        ref(__("Payment disputed", "woocommerce-payments"));

      switch (disputeReasons->Js.Dict.get(event.reason)) {
      | None => ()
      | Some(reason) =>
        reasonHeadline :=
          __("Payment disputed as %s", "woocommerce-payments")
          ->sprintf1(reason.display)
      };

      let disputeUrl =
        addQueryArgs(
          "admin.php",
          QueryArgs.make(
            ~page="wc-admin",
            ~path="/payments/disputes/details",
            ~id=event.dispute_id,
            (),
          ),
        );

      let depositTimelineItem =
        switch (event.amount->Js.Nullable.toOption) {
        | None => {
            date: Js.Date.fromFloat(event.datetime *. 1000.),
            icon: <Gridicon icon="info-outline" />,
            headline:
              __("No funds have been withdrawn yet", "woocommerce-payments")
              ->React.string,
            body: [|
              __(
                "The cardholder's bank is requesting more information to decide whether to return these funds to the cardholder.",
                "woocommerce-services",
              )
              ->React.string,
            |],
            hideTimestamp: true,
          }
        | Some(amount) =>
          let formattedTotal =
            formatCurrency(
              Js.Math.abs_int(amount) + Js.Math.abs_int(event.fee),
            );

          event->makeDepositTimelineItem(
            ~formattedAmount=formattedTotal,
            ~isPositive=false,
            ~body=[|
              __("Disputed amount: %s", "woocommerce-payments")
              ->stringWithAmount(amount)
              ->React.string,
              __("Fee: %s", "woocommerce-payments")
              ->stringWithAmount(event.fee)
              ->React.string,
            |],
            (),
          );
        };

      [|
        event->makeMainTimelineItem(
          ~headline=(reasonHeadline^)->React.string,
          ~icon="cross",
          ~iconClass="is-error",
          ~body=[|
            <a href=disputeUrl>
              {__("View dispute", "woocommerce-payments")->React.string}
            </a>,
          |],
          (),
        ),
        depositTimelineItem,
        event->makeStatusChangeTimelineItem(
          __("Disputed: Needs Response", "woocommerce-payments"),
        ),
      |];
    | "dispute_in_review" => [|
        event->makeMainTimelineItem(
          ~headline=
            __("Challenge evidence submitted", "woocommerce-payments")
            ->React.string,
          ~icon="checkmark",
          ~iconClass="is-success",
          (),
        ),
        event->makeStatusChangeTimelineItem(
          __("Disputed: In Review", "woocommerce-payments"),
        ),
      |]
    | "dispute_won" =>
      let formattedTotal =
        formatCurrency(
          Js.Math.abs_int(
            event.amount->Js.Nullable.toOption->Belt.Option.getWithDefault(0),
          )
          + Js.Math.abs_int(event.fee),
        );
      [|
        event->makeMainTimelineItem(
          ~headline=
            __(
              "Dispute won! The bank ruled in your favor",
              "woocommerce-payments",
            )
            ->React.string,
          ~icon="notice-outline",
          ~iconClass="is-success",
          (),
        ),
        event->makeDepositTimelineItem(
          ~formattedAmount=formattedTotal,
          ~isPositive=true,
          ~body=[|
            __("Disputed amount: %s", "woocommerce-payments")
            ->stringWithAmount(
                event.amount
                ->Js.Nullable.toOption
                ->Belt.Option.getWithDefault(0),
              )
            ->React.string,
            __("Fee: %s", "woocommerce-payments")
            ->stringWithAmount(event.fee)
            ->React.string,
          |],
          (),
        ),
        event->makeStatusChangeTimelineItem(
          __("Disputed: Won", "woocommerce-payments"),
        ),
      |];
    | "dispute_lost" => [|
        event->makeMainTimelineItem(
          ~headline=
            __(
              "Dispute lost. The bank ruled in favor of your customer",
              "woocommerce-payments",
            )
            ->React.string,
          ~icon="cross",
          ~iconClass="is-error",
          (),
        ),
        event->makeStatusChangeTimelineItem(
          __("Disputed: Lost", "woocommerce-payments"),
        ),
      |]
    | "dispute_warning_closed" => [|
        event->makeMainTimelineItem(
          ~headline=
            __(
              "Dispute inquiry closed. The bank chose not to pursue this dispute.",
              "woocommerce-payments",
            )
            ->React.string,
          ~icon="notice-outline",
          ~iconClass="is-success",
          (),
        ),
      |]
    | "dispute_charge_refunded" => [|
        event->makeMainTimelineItem(
          ~headline=
            __(
              "The disputed charge has been refunded.",
              "woocommerce-payments",
            )
            ->React.string,
          ~icon="notice-outline",
          ~iconClass="is-success",
          (),
        ),
      |]
    | _ => [||]
    };
  };
};

[@genType]
let default = timelineEvents => {
  switch (timelineEvents) {
  | None => [||]
  | Some(events) => events->Util.Lodash.flatMap(Util.mapEventToTimelineItems)
  };
};
