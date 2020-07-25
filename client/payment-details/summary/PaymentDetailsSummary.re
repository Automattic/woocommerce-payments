module Card = {
  [@bs.module "@woocommerce/components"] [@react.component]
  external make:
    (~className: string, ~children: React.element) => React.element =
    "Card";
};

module Button = {
  [@bs.module "@wordpress/components"] [@react.component]
  external make:
    (
      ~className: string,
      ~isDefault: bool,
      ~isLarge: bool,
      ~href: string,
      ~children: React.element
    ) =>
    React.element =
    "Button";
};

[@genType.import "@wordpress/i18n"]
external __: (string, string) => string = "__";

[@genType]
type moment;
[@genType.import "@wordpress/date"]
external dateI18n: (string, moment) => string = "dateI18n";

[@genType.import ("moment", "default")]
external moment: float => moment = "moment";

[@bs.module "./style.scss"] external _style: string => string = "style";

type currency = {formatCurrency: float => string};
[@bs.new] [@bs.module "@woocommerce/currency"]
external createCurrency: unit => currency = "default";

let currency = createCurrency();

type summaryItem = {
  title: string,
  content: React.element,
};

let composeSummaryItems = (charge: Charge.t) => [|
  {
    title: __("Date", "woocommerce-payments"),
    content:
      charge.created > 0.0
        ? dateI18n("M j, Y, g:ia", (charge.created *. 1000.0)->moment)
          ->React.string
        : {js|–|js}->React.string,
  },
  {
    title: __("Customer", "woocommerce-payments"),
    content:
      charge.billing_details.name
      ->Belt.Option.getWithDefault("–")
      ->React.string,
  },
  {
    title: __("Payment method", "woocommerce-payments"),
    content: <ShortPaymentMethod payment={charge.payment_method_details} />,
  },
  {
    title: __("Risk evaluation", "woocommerce-payments"),
    content:
      switch (
        charge.outcome
        ->Belt.Option.map(o => o.risk_level)
        ->Belt.Option.getWithDefault(Normal)
      ) {
      | Normal => __("Normal", "woocommerce-payments")->React.string
      | Elevated => __("Elevated", "woocommerce-payments")->React.string
      | Highest => __("Highest", "woocommerce-payments")->React.string
      | Unknown => {js|–|js}->React.string
      },
  },
  {
    title: __("", "woocommerce-payments"),
    content:
      "" == charge.id ? {js|–|js}->React.string : charge.id->React.string,
  },
|];

[@genType]
[@react.component]
let make = (~charge, ~isLoading) => {
  let {net, fee, refunded}: Charge.Util.chargeAmounts =
    charge->Charge.Util.getAmounts;

  <Card className="payment-details-summary-details">
    <div className="payment-details-summary">
      <div className="payment-details-summary__section">
        <p className="payment-details-summary__amount">
          <Loadable isLoading placeholder={"Amount placeholder"->React.string}>
            {currency.formatCurrency(charge.amount->float_of_int /. 100.0)
             ->React.string}
            <span className="payment-details-summary__amount-currency">
              ("" == charge.currency ? "cur" : charge.currency)->React.string
            </span>
            <PaymentStatusChip status={charge->PaymentStatus.fromCharge} />
          </Loadable>
        </p>
        <div className="payment-details-summary__breakdown">
          {refunded > 0
             ? <p>
                 {(
                    __("Refunded", "woocommerce-payments")
                    ++ ": "
                    ++ currency.formatCurrency(
                         (- refunded)->float_of_int /. 100.0,
                       )
                  )
                  ->React.string}
               </p>
             : React.null}
          <p>
            <Loadable isLoading placeholder={"Fee amount"->React.string}>
              {(
                 __("Fee", "woocommerce-payments")
                 ++ ": "
                 ++ currency.formatCurrency((- fee)->float_of_int /. 100.0)
               )
               ->React.string}
            </Loadable>
          </p>
          <p>
            <Loadable isLoading placeholder={"Net amount"->React.string}>
              {(
                 __("Net", "woocommerce-payments")
                 ++ ": "
                 ++ currency.formatCurrency(net->float_of_int /. 100.0)
               )
               ->React.string}
            </Loadable>
          </p>
        </div>
      </div>
      <div className="payment-details-summary__section">
        /* TODO: implement control buttons depending on the transaction status */

          <div className="payment-details-summary__actions">
            {charge.order
             ->Belt.Option.map(order =>
                 <Button
                   className="payment-details-summary__actions-item"
                   isDefault=true
                   isLarge=true
                   href={order.url}>
                   {(
                      __("View order", "woocommerce-payments")
                      ++ " #"
                      ++ order.number
                    )
                    ->React.string}
                 </Button>
               )
             ->Belt.Option.getWithDefault(React.null)}
          </div>
        </div>
    </div>
    <hr className="full-width" />
    <LoadableBlock isLoading numLines=4>
      <HorizontalList items={charge->composeSummaryItems} />
    </LoadableBlock>
  </Card>;
};

[@genType]
let default = make;
