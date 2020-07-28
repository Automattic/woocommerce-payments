[@genType]
[@react.component]
let make = (~label=React.null, ~isLoading=false, ~children=React.null) => {
  <div className="payment-method-detail">
    <h4 className="payment-method-detail__label">
      <Loadable isLoading display="block" value=label />
    </h4>
    <p className="payment-method-detail__value">
      <Loadable isLoading value=children />
    </p>
  </div>;
};

[@genType]
let default = make;
