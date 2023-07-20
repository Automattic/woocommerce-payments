## Order Meta Helper

The purpose of this helper is to allow merchants to fix or update the Multi-Currency exchange rates on orders if/when they are incorrect.

### Usage

* Go to WooCommerce > Status > Tools
* Find the _Enable/Disable Multi-Currency Meta Helper_ option and click the button. You should then receive a notice that the tool was enabled.
* Go to the order that has an issue with the Multi-Currency exchange rate.
* A new meta box named _Multi-Currency Meta Helper_ will appear in the main column near the bottom.
  * This will only show for orders where the currency does not match the store currency. An example would be if the store currency is USD and the order currency is EUR.
* There will be information displayed about the order in the meta box.
  * If either the Multi-Currency or Stripe exchange rate are not found, they will display _Not Found_.
  * If the order is through WooPayments, and if a charge is found, then there will be a _Charge Exchange Rate_ shown. If the _Charge Exchange Rate_ is found, it will also display as a _Suggested Exchange Rate_.
* Enter the new exchange rate to be used into the field, and then click the _Update_ button for the order.
* A new order note will appear stating that the Multi-Currency exchange rate has been updated.

### Additional Note

This tool is for edge cases where this data may be missing, which can cause issues with Analytics reporting. Once the exchange rate is updated, the _Import historical data_ tool under Analytics > Settings will need to be run. This will correct data related to any orders that have been adjusted.
