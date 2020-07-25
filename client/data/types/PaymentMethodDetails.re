type t = {
  card: option(Card.t),
  [@bs.as "type"]
  type_: PaymentMethodType.t,
};

let make = (~card=None, ~type_=PaymentMethodType.Card, ()): t => {
  card,
  type_,
};
