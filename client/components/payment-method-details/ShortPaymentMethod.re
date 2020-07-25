[@bs.module "./style.scss"] external _asdf: string => string = "style";

[@genType]
let make = (~payment: PaymentMethodDetails.t) =>
  switch (payment.type_) {
  | Card =>
    payment.card
    ->Belt.Option.map(card =>
        <span className="payment-method-details">
          /* TODO: deal with other payment methods. Currently this assumes payment type is card */

            <span
              className={
                "payment-method__brand payment-method__brand--" ++ card.brand
              }
            />
            {js|\u0020••••\u0020|js}->React.string
            card.last4->React.string
          </span>
      )
    ->Belt.Option.getWithDefault(<span> "&ndash;"->React.string </span>)
  };

[@genType]
let default = make;
