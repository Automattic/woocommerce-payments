type query
type function

@bs.module("@woocommerce/navigation")
external getQuery: unit => query = "getQuery"
@bs.module("@woocommerce/navigation")
external onQueryChange: function = "onQueryChange"
