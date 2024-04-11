# General Conventions

## Prefer named exports

We should prefer named exports over default exports because they offer some advantages:

-   A predictable names used across the codebase.
-   Better editor integration support (thanks to predictable names).

### ✅ **Do** use named exports

```ts
// exporter.ts
export const aValue = 42;
// importer.ts
import { aValue } from './exporter';
```

### 👎 **Avoid** default exports

```ts
// exporter.ts
export default 42;
// importer.ts
import iCanPutWhateverNameHere from './exporter';
```

## Use JSDoc to provide relevant documentation

JSDoc still has a place for highly-visible, relevant documentation.

You can add documentation on types, interfaces, interface properties, enums and their members… Use
this, you'll thank yourself!

```ts
interface Props {
	/** Helpful detail for a confusing prop */
	whatIsThisFor?: number | Symbol;
}
```

### ✅ _Do_ use JSDoc to add descriptions and relevant information

Adding descriptive JSDoc documentation is an excellent way to support ourselves as developers
working on the codebase. Editor integrations will put this documentation at your fingertips thanks
to the TypeScript language server!

```ts
/** This is a very special number */
const SPECIAL_NUMBER = 42;

/**
 * This is the K-combinator, also known as Kestrel.
 *
 * Purists will note the returned function should accept exactly 1 argument but instead we accept
 * an arbitrary number.
 *
 * @param x The value that will be returned from the returned function
 * @return A function that always returns the provided value
 */
function kestrel< T >( x: T ): ( ..._: unknown[] ) => T {
	return () => x;
}
```

### ⛔️ _Don't_ add types in JSDoc

This is redundant and better done with TypeScript syntax.

```ts
/**
 * Square a number. The types in this JSDoc are an example of what NOT to do.
 *
 * @param {number} n DO NOT INCLUDE THE {number} HERE!
 * @return {number} n*n DO NOT INCLUDE THE {number} HERE!
 */
function square( n: number ): number {
	return n * n;
}
```

## Rely on type inference

TypeScript has a very powerful type system that can infer a lot from the code we write. Do not
annotate every type in the application, especially trivial things. Instead, rely on inference and
try to structure things in a way that empowers inference. Often, a few types go a long way.

Try to keep the interfaces you do write close to their natural origin. For example, when you write a
`Props` interface put it in the same module as the component.

## Avoid `any`

The `any` type is dangerous and should be avoided. It makes it impossible for the type system to
discover issues or provide helpful information in many cases.

`unknown` is a safer alternative to `any` that may be helpful.

```ts
// ⛔️ any is dangerous. Do not use it!
const x: any = 0;
const y: string = x; // The type system thinks this is fine! 😱

// ✅ unknown is a good alternative to any.
const x2: unknown = produceSomeUntypedExternalValue();
const y2: string = x2; // 😌 Type error. The type system correctly doesn't trust this to be a string.
```

## Avoid type assertions

A _type assertion_ looks like this: `const x = 42 as string` or `const x = <string>42`, although the
latter form is less common due to the similar or ambiguous syntax with JSX. Some folks call these
"casts" or "coercion."

Type assertions are very dangerous and should be avoided. They override the type system and tell it
that we know better.

If you need to add a type to something, instead of an assertion try an _annotation_, like this:
`const x: number = 42`. This is help for TypeScript, it will use the annotated type but will also
check that it is valid. But don't annotate an assignment like this, TypeScript will infer it 🙂

## Use readonly

We often rely on immutable data, we can encode this in the type system in a few ways.

First, when defining an interface we can be clear about readonly properties to make our interface
immutable:

```ts
interface CantTouchThis {
	readonly string: string;
	readonly array: ReadonlyArray< string >;
	readonly tuple: readonly [ string, string ];
	readonly object: {
		readonly prop: number;
	};
}
```

This also works in classes:

```ts
class Klassy {
	readonly immutable: string;
	mutable: number;

	constructor() {
		this.immutable = 'define it here, last chance';
		this.mutable = 0;
	}

	tick() {
		this.mutable += 1; // ✅ Ok
		this.immutable = 'whoops'; // ⛔️ Type error, that's readonly!
	}

	// Yes, these arrow functions are instance properties too and can be readonly 👀
	readonly noop = () => undefined;
}
```

And did you see that `ReadonlyArray<T>`? That's a great alternative to `Array<T>` or `T[]`. There
are also `ReadonlySet<T>` and `ReadonlyMap<Key, Value>` types!

## Use const assertions

`const` assertions are an excellent tool to prevent TS from _widening_ inferred types. This can sound a bit abstract so consider the following code:

```ts
function getShapes() {
	return [
		{ kind: 'circle', radius: 100 },
		{ kind: 'square', sideLength: 50 },
	];
}

function useRadius( radius: number ) {
	return radius;
}

for ( const shape of getShapes() ) {
	if ( shape.kind === 'circle' ) {
		// TS still thinks shape can be any of the items returned from 'getShapes()' and thus (correctly) infers that 'shape.radius' may be 'undefined'.
		useRadius( shape.radius ); // ⛔️ Can't pass 'number | undefined' when the function expects a 'number'.
	}
}
```

`const` assertions allow us to get a concrete type without resorting to type guards or type assertion:

```ts
function getShapes() {
	return [
		{ kind: 'circle', radius: 100 },
		{ kind: 'square', sideLength: 50 },
	] as const; // 💡 Add the const assertion here.
}

function useRadius( radius: number ) {
	return radius;
}

for ( const shape of getShapes() ) {
	if ( shape.kind === 'circle' ) {
		useRadius( shape.radius ); // ✅ Ok, TS knows that if kind === 'circle' then 'shape' must have a 'radius' prop!
	}
}
```

Not everything needs a _const assertion,_ but when we want to infer a readonly interface it's a
great option. Here's what that might look like:

```ts
function actionMaker( direction: 'up' | 'down' ) {
	return { type: 'GO', direction } as const; // Here is our const assertion: `as const`
}
```

This function has the following return type, inferred without writing a bunch of types. Perfect!
We've leveraged inference nicely:

```ts
type ReturnType = {
	readonly type: 'GO';
	readonly direction: 'up' | 'down';
};
```

## Avoid enums

An [enum](https://www.typescriptlang.org/docs/handbook/enums.html) represents a set of named constants. Avoid using enums in TypeScript due to their [problematic tradeoffs](https://blog.logrocket.com/why-typescript-enums-suck/) – enum values are not type-safe, exhibit surprising behavior/have volatile values and can lead to runtime errors.

Consider using string unions or objects with const assertions instead.

-   Instead of a simple enum `enum Status { On, Off }` use a string union:

    `type Status = 'on' | 'off';`

-   If you really need an object, use an object with a const assertion:

    `const Status = { On: 'on', Off: 'off' } as const;`
