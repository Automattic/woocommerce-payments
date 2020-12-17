module Components = Externals_WooCommerce_Components
module Navigation = Externals_WooCommerce_Navigation

module Currency = {
  type t

  @bs.module("@woocommerce/currency") @bs.new external createCurrency: unit => t = "default"

  @bs.send external formatAmount: (t, float) => string = "formatAmount"
}

module Date = {
  @bs.module("@wordpress/date")
  external dateI18n: (string, string, bool) => string = "dateI18n"
}

module I18n = {
  @bs.module("@wordpress/i18n")
  external __: (string, string) => string = "__"
}
