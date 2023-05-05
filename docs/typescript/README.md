# WooPayments TypeScript Guidelines

This set of guidelines aims to make it easier for developers working on WooPayments to get started using TypeScript, as well as make it easy to work through common hurdles when writing TypeScript.

## Table of Contents

- [Should I use `interface` or `type` when declaring types?](./interface-vs-type.md)
- [How do I write React components in TypeScript?](./react-components.md)
- [Where should I declare my types?](./declaring-types.md)

## External resources
- [Cheat Sheets](https://www.typescriptlang.org/cheatsheets)
- [Utility Types](https://www.typescriptlang.org/docs/handbook/utility-types.html) – Common type transformations that make them easier to write. Such as:
  - `Partial<Type>` – Makes all properties `optional`.
  - `Record<Keys, Type>` – Constructs an object types whose property keys are `Keys` and whose property values are `Type`.
  - `Pick<Type>` – Picks a set of properties.
  - `Return<Type>` – Constructs a type consisting of the return type of function `Type`.
- [Keyof Type Operator](https://www.typescriptlang.org/docs/handbook/2/keyof-types.html) – Takes an object type and produces a literal union of its keys.
- [Typeof Type Operator](https://www.typescriptlang.org/docs/handbook/2/typeof-types.html) – Refers to the type of a variable or property.
- [Indexed Access Types](https://www.typescriptlang.org/docs/handbook/2/indexed-access-types.html) – Looks up a specific property on another type.
- [Template Literal Types](https://www.typescriptlang.org/docs/handbook/2/template-literal-types.html) – Allows for narrowing down the type to the exact value.
