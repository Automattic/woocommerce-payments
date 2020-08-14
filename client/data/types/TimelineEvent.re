// Temporary stand-in module.
module Deposit = {
  type t = {
    id: string,
    arrival_date: float,
  };
};

type t = {
  datetime: float,
  currency: string,
  amount_refunded: int,
  amount: Js.Nullable.t(int),
  fee: int,
  reason: string,
  dispute_id: string,
  deposit: Js.Nullable.t(Deposit.t),
  [@bs.as "type"]
  type_: string,
};
