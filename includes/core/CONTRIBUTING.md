# Contributing to WooCommerce Payments Core

The [main readme file](README.md) in this directory outlines the concept of WooCommerce Payments' core. Please familiarize yourself with it before proceeding with this document.

## Existing and additional core functionality

The initial (and so far current) contents of this directory, as described in README.md is the outcome of the initial phase of the WooCommerce Payments as a Platform project. However, in addition to further phases of the project, further contributions to the plugin should also strive to follow the same principles.

### Services

There are a few possible paths when it comes to services:

1. __Create a facade for an existing service:__ Create a new service class within `core/service`, which simply facades the [existing service](service/customer-service.md). Doing so will allow us to modify the facade in the future, keeping existing methods with the same parameters as existing ones.
This is what was done with the [customer service](service/customer-service.md), and is the recommended way if a certain feature requires access to an existing service quickly.
2. __Move an existing service to the core directory:__ This should be done with consideration how the service could change in the future, and whether it is core to the gateway. If it more suitable to an extension (ex. [Multi-Currency](https://woocommerce.com/document/woocommerce-payments/currencies/multi-currency-setup/?quid=92bb9bc4a89c89c9445c87865165e025)), or a consumer (ex. [WooPay](https://woocommerce.com/documentation/woopay/)), it likely needs to be somewhere else.
3. When __creating a new service__, similarly to moving existing ones here, please consider whether the service belongs to core. If it does, do it with care, as services should be reliable and resilient.

🔗 Further information about services in core is available [within the services directory](services/README.md).

### Communication with the server

The bulk of the communication between the client (plugin) and server is currently done through the `WC_Payments_API_Client` class.

The foundation for request and response classes has already been laid down. However, not all requests have been converted to classes yet, and there is only a generic response class available for the moment.

Whenever creating new requests, please consider using request classes for them.

[🔗 Read more about server requests](server/README.md)
[🔗 Guide for creating server requests classes](server/CONTRIBUTING.md)

## Using core functionality

While working on WooCommerce Payments, please consider the concept of core vs non-core functionality. A lot of projects could be considered a part of WooCommerce Payments, WooPay being a good example.  It's a part of WooCommerce Payments, but is not a part of core.

When using core APIs within isolated code, it will allow new developers to understand fewer products, and decrease the likelyhoold of side-effects.
