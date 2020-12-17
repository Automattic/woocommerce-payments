module Hooks = Data_Deposits_Hooks

module Strings = {
  let displayType = Js.Dict.fromArray([
    ("deposit", Externals_WooCommerce.I18n.__("Deposit", "woocommerce-payments")),
    ("withdrawal", Externals_WooCommerce.I18n.__("Withdrawal", "woocommerce-payments")),
  ])

  let displayStatus = Js.Dict.fromArray([
    ("paid", Externals_WooCommerce.I18n.__("Paid", "woocommerce-payments")),
    ("pending", Externals_WooCommerce.I18n.__("Pending", "woocommerce-payments")),
    ("in_transit", Externals_WooCommerce.I18n.__("In Transit", "woocommerce-payments")),
    ("canceled", Externals_WooCommerce.I18n.__("Canceled", "woocommerce-payments")),
    ("failed", Externals_WooCommerce.I18n.__("Failed", "woocommerce-payments")),
    ("estimated", Externals_WooCommerce.I18n.__("Estimated", "woocommerce-payments")),
  ])
}
