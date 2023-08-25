# Where should I declare my types?

## Component props

Component props should be declared in the same file you implement the component:

```ts
interface Props {
	// ...
}

const MyAwesomeComponent: React.FunctionComponent< Props > = ( props ) => {
	// ...
};
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
	};
}
```

## Module declarations

External module declarations, such as a dependency that's missing types or an import extension like `*?asset`, should have their own `module-name.d.ts` file stored in `client/types/declarations`.

For example, this is how we add types to our asset files:

```ts
// client/types/declarations/assets.d.ts

declare module '*?asset' {
	const src: string;
	export default src;
}
```

and this is how we declare types for Gridicons:

```ts
declare module 'gridicons/dist/*' {
	type GridiconParams = {
		size?: number;
		className?: string;
	};
	const Gridicon: ( props: GridiconParams ) => JSX.Element;

	export = Gridicon;
}
```

Generally speaking, we add types to any external libraries using the following "template":

```ts
import '<external_or_npm_package_name>';

declare module '<external_or_npm_package_name>' {
	// Declare types, functions, globals, etc.
}
```

Notice how we’re importing the external library at the top, even though we're not using it. If we don’t import it first, our module declaration overrides the module declared in the external library’s index.d.ts definitions file, whereas we generally want to build on top of the definitions (when they exist).

Why does it work that way? ([reference](https://www.typescriptlang.org/docs/handbook/2/modules.html#how-javascript-modules-are-defined))

-   In TypeScript, a file without any top-level import or export declarations is treated as a script, and so what is declared in such file is added to the global scope, thus overriding any existing declaration.
-   When you add an import, the script becomes a module, and what you define inside the file is now scoped to that module.

Taking a theoretical currency formatting npm package called `woo-currency-formatter` that has a method `formatAmountWithCurrency` we would declare the types as follows:

```ts
import 'woo-currency-formatter';

declare module 'woo-currency-formatter' {
	/**
	 * Used to make sure we only accept known currencies when formatting amounts.
	 */
	type Currency = 'usd' | 'cad' | 'isk' | 'eur' /* ... */;

	const formatAmountWithCurrency: (
		amount: number,
		currency: Currency
	) => string;
}
```
