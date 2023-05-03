# How do I write React components in TypeScript?

## Declare the attributes on the component with an `interface`

```ts
interface Props {
  /**
   * Set the loading state of the component. Provide `true` if the component should show a loading state.
   * @type boolean
   */
  isLoading: boolean;
  
  /**
   * The first name to display.
   * @type string
   */
  firstName: string;
  
  /**
   * The last name to display.
   * @type string
   */
  lastName: string;
};
```

We provide JSDoc comments to further describe what each attribute does.
This information will be available in an editor's autocomplete when you're using the component elsewhere.

## Declare the component using the attribute type you declared in your `interface`

```ts
const DisplayName: React.FunctionComponent< Props > = ( props ) => {
  // TypeScript knows props.isLoading is a boolean.
  if ( props.isLoading ) {
      // ...
  }

  // ...
}
```

You may notice something weird with the declaration.
What is that `React.FunctionComponent< Props >`?
`React.FunctionComponent` is a convenience type that we encourage you to use.
You can also use the shorthand version: `React.FC`.

`React.FunctionComponent` provides a type that includes set of default attributes and ensures you return the right value from the component function.
For example, if you try to return `true` in the component you'll get an error:

```ts
// ERROR: Type 'boolean' is not assignable to type 'ReactElement<any, any>'.
const DisplayName: React.FunctionComponent< Props > = ( props ) => {
  return true;
}
```

So the `React.FunctionComponent` type helps keep us honest when writing a component, and makes sure we return something that makes sense for a React component.

If you prefer to destructure the attributes provided to the component you can do that as well:

```ts
const DisplayName: React.FC< Props > = ( { isLoading, numRows, numColumns } ) => {
  // ...
}
```

## Implement the rest of the component

In most cases you can then fill in the component without worrying about the types since TypeScript will infer types for all of the variables you use for you.

A complete example might look something like this:

```ts
import React from 'react';

interface Props {
  /**
   * Set the loading state of the component. Provide `true` if the component should show a loading state.
   * @type boolean
   */
  isLoading: boolean;
  
  /**
   * The first name to display.
   * @type string
   */
  firstName: string;
  
  /**
   * The last name to display.
   * @type string
   */
  lastName: string;
};

export const DisplayName: React.FC< Props > = ( { isLoading, numRows, numColumns } ) => {
  if ( isLoading ) {
    return <p>Loading...</p>;
  }

  return <p>{ firstName } { lastName }</p>;
}
```

