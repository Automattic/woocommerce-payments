type riskLevel =
  | Normal
  | Elevated
  | Highest
  | Unknown;

type t = {
  [@bs.as "type"]
  type_: OutcomeType.t,
  risk_level: riskLevel,
};

let make = (~type_=OutcomeType.Authorized, ~risk_level=Normal, ()) => {
  type_,
  risk_level,
};
