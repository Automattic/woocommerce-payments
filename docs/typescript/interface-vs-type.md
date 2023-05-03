# Should I use `interface` or `type` when declaring types?

Always prefer `interface`.

```ts
// Avoid:
type Props = {
  paymentIntentId: string;
  isLoading: bool;
}

// Instead do:
interface Props = {
  paymentIntentId: string;
  isLoading: bool;
}
```

The biggest reason to prefer `interface` is that they're more flexible.
They can be extended to add more properties when needed whereas types cannot.
The TypeScript Handbook itself [recommends](https://www.typescriptlang.org/docs/handbook/2/everyday-types.html#differences-between-type-aliases-and-interfaces) using `interface` unless there's a specific feature you need from `type`, in which case the TypeScript compiler will likely tell you about it:

> For the most part, you can choose based on personal preference, and TypeScript will tell you if it needs something to be the other kind of declaration. If you would like a heuristic, use interface until you need to use features from type.


## What might be a case where I need to use `type` instead of `interface`?

The one useful case for this is renaming a primitive type such as `string` or `number`.
For example, you might want to make it easier for whoever is reading the code that the `string` some function accepts is a Payment Intent ID, so instead of using `string` you'd prefer to use `PaymentIntentID`:

```ts
interface PaymentIntentID = string; // Error: can't assign type primitive to an interface.

type PaymentIntentID = string;

function foo( intent: PaymentIntentID ) {
  // ...
}
```
