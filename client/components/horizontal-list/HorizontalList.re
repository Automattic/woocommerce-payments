module List = {
  [@bs.module "@woocommerce/components"] [@react.component]
  external make: (~items: array('a), ~className: string) => React.element =
    "List";
};

[@bs.module "./style.scss"] external _style: string => string = "style";

[@genType]
[@react.component]
let make = (~items=[||]) =>
  <List className="woocommerce-list--horizontal" items />;

[@genType]
let default = make;
