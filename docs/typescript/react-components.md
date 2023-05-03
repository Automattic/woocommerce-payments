# How do I write React components in TypeScript?

## Declare the attributes on the component with an `interface`

First, define the attributes for your component using an `interface`:

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

## Implement the component using the attribute type you declared in your `interface`

Now use the `interface` you just declared when you implement the component:

```ts
const DisplayName: React.FunctionComponent< Props > = ( props ) => {
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
const DisplayName: React.FunctionComponent< Props > = ( props ) => { // ERROR: Type 'boolean' is not assignable to type 'ReactElement<any, any>'.
  return true;
}
```

So the `React.FunctionComponent` type helps keep us honest when writing a component, and makes sure we return something that makes sense for a React component.

If you prefer to destructure the attributes provided to the component you can do that as well:

```ts
const Table: DisplayName.FC< Props > = ( { isLoading, numRows, numColumns } ) => {
  if ( isLoading ) {
    // ...
  }

  // ...
}
```

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

const Table: React.FC< Props > = ( { isLoading, numRows, numColumns } ) => {
  if ( isLoading ) {
    return <p>Loading...</p>;
  }

  return <p>{ firstName } { lastName }</p>;
}
```

