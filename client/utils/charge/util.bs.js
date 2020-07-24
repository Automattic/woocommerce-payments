// Generated by BUCKLESCRIPT, PLEASE EDIT WITH CARE

import * as List from "bs-platform/lib/es6/list.js";

var failedOutcomeTypes = {
  hd: "issuer_declined",
  tl: {
    hd: "invalid",
    tl: /* [] */0
  }
};

var blockedOutcomeTypes = {
  hd: "blocked",
  tl: /* [] */0
};

function getChargeOutcomeType(charge) {
  var o = charge.outcome;
  if (o !== undefined) {
    return o.type;
  } else {
    return "";
  }
}

function isChargeBlocked(charge) {
  if ("failed" === charge.status) {
    return List.exists((function (t) {
                  return getChargeOutcomeType(charge) === t;
                }), blockedOutcomeTypes);
  } else {
    return false;
  }
}

function isChargeFailed(charge) {
  if ("failed" === charge.status) {
    return List.exists((function (t) {
                  return getChargeOutcomeType(charge) === t;
                }), failedOutcomeTypes);
  } else {
    return false;
  }
}

function isChargeDisputed(charge) {
  return charge.disputed === true;
}

function isChargeRefunded(charge) {
  return charge.amount_refunded > 0;
}

function isChargeFullyRefunded(charge) {
  return charge.refunded;
}

function isChargePartiallyRefunded(charge) {
  if (charge.amount_refunded > 0) {
    return !charge.refunded;
  } else {
    return false;
  }
}

function isChargeSuccessful(charge) {
  if ("succeeded" === charge.status) {
    return charge.paid;
  } else {
    return false;
  }
}

function mapDisputeStatusToChargeStatus(status) {
  switch (status) {
    case "lost" :
        return /* DisputeLost */5;
    case "needs_response" :
    case "warning_needs_response" :
        return /* DisputeNeedsResponse */2;
    case "under_review" :
    case "warning_under_review" :
        return /* DisputeUnderReview */3;
    case "won" :
        return /* DisputeWon */4;
    default:
      return /* Disputed */6;
  }
}

function getChargeStatus(charge) {
  if (isChargeFailed(charge)) {
    return {
            TAG: /* Ok */0,
            _0: /* Failed */0
          };
  }
  if (isChargeBlocked(charge)) {
    return {
            TAG: /* Ok */0,
            _0: /* Blocked */1
          };
  }
  if (!charge.disputed) {
    if (isChargePartiallyRefunded(charge)) {
      return {
              TAG: /* Ok */0,
              _0: /* PartiallyRefunded */7
            };
    } else if (charge.refunded) {
      return {
              TAG: /* Ok */0,
              _0: /* FullyRefunded */8
            };
    } else if (isChargeSuccessful(charge)) {
      if (charge.captured) {
        return {
                TAG: /* Ok */0,
                _0: /* Paid */9
              };
      } else {
        return {
                TAG: /* Ok */0,
                _0: /* Authorized */10
              };
      }
    } else {
      return {
              TAG: /* Error */1,
              _0: /* NoPaymentStatus */0
            };
    }
  }
  var d = charge.dispute;
  if (d !== undefined) {
    return {
            TAG: /* Ok */0,
            _0: mapDisputeStatusToChargeStatus(d.status)
          };
  } else {
    return {
            TAG: /* Ok */0,
            _0: /* Disputed */6
          };
  }
}

export {
  failedOutcomeTypes ,
  blockedOutcomeTypes ,
  getChargeOutcomeType ,
  isChargeBlocked ,
  isChargeFailed ,
  isChargeDisputed ,
  isChargeRefunded ,
  isChargeFullyRefunded ,
  isChargePartiallyRefunded ,
  isChargeSuccessful ,
  mapDisputeStatusToChargeStatus ,
  getChargeStatus ,
  
}
/* No side effect */
