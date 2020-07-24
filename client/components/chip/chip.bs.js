// Generated by BUCKLESCRIPT, PLEASE EDIT WITH CARE

import * as React from "react";
import * as $$String from "bs-platform/lib/es6/string.js";
import * as Belt_List from "bs-platform/lib/es6/belt_List.js";

function chipClass(chipType) {
  switch (chipType) {
    case /* Light */1 :
        return "chip-light";
    case /* Warning */2 :
        return "chip-warning";
    case /* Alert */3 :
        return "chip-alert";
    case /* Primary */0 :
    case /* Default */4 :
        return "chip-primary";
    
  }
}

function Chip(Props) {
  var messageOpt = Props.message;
  var chipTypeOpt = Props.chipType;
  var isCompatOpt = Props.isCompat;
  var message = messageOpt !== undefined ? messageOpt : "";
  var chipType = chipTypeOpt !== undefined ? chipTypeOpt : /* Default */4;
  var isCompat = isCompatOpt !== undefined ? isCompatOpt : false;
  var classNames_1 = {
    hd: chipClass(chipType),
    tl: {
      hd: isCompat ? "is-compat" : "",
      tl: /* [] */0
    }
  };
  var classNames = {
    hd: "chip",
    tl: classNames_1
  };
  return React.createElement("span", {
              className: $$String.trim(Belt_List.reduce(classNames, "", (function (acc, curr) {
                          return acc + (" " + curr);
                        })))
            }, message);
}

var make = Chip;

export {
  chipClass ,
  make ,
  
}
/* react Not a pure module */
