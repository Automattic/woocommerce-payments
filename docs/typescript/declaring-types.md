# Where should I declare my types?

## Component props

Component props should be declared in the same file you implement the component:

```ts
interface Props {
  // ...
}

const MyAwesomeComponent: React.FunctionComponent< Props > = ( props ) => {
  // ...
}
```

## Global constants

Global constants such as the variables we load in from PHP and live on the `window` object go into `client/types/globals.d.ts`.
For example, `wcpaySettings` is declared there:

```ts
declare const wcpaySettings {
  connectUrl: string;
  isSubscriptionsActive: boolean;
  //...
}
```

## Shared types

Types that are used throughout the codebase, such as `PaymentIntent` or `Dispute` should have a dedicated type declaration file `type-name.d.ts` that's saved in `client/types`.
We recommend you organize the types into a file hierarchy that makes sense, for example a `PaymentIntent` type might go into `client/types/stripe/payment-intent.d.ts` or `client/types/transactions/payment-intent.d.ts`.

For example:

```ts
// client/types/stripe/payment-intent.d.ts

export interface PaymentIntent {
  id: string;
  object: 'payment_intent';
  // ...
}
```
