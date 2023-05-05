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

## Global constants

Global constants such as the variables we load in from PHP and live on the `window` object go into `client/types/globals.d.ts`.
For example, `wcpaySettings` is declared there:

```ts
declare global {
  const wcpaySettings: {
    connectUrl: string;
    isSubscriptionsActive: boolean;
    //...
  }
}
```

## Module declarations

External module declarations, such as a dependency that's missing types or an import extension like `*?asset`, should have their own `module-name.d.ts` file stored in `client/types/declarations`.

For example:

```ts
// client/types/declarations/assets.d.ts

declare module '*?asset' {
	const src: string;
	export default src;
}
```
