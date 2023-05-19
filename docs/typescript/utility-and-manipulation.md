# Utility types and manipulation

## Utility types
Common type transformations that make them easier to write. You can find more on [TS docs](https://www.typescriptlang.org/docs/handbook/utility-types.html).

### `Partial<Type>` – Makes all properties `optional`
```ts
interface Todo {
  title: string;
  description: string;
  completed: boolean;
}

type TodoUpdate = Partial<Todo>
// Same as
interface TodoPreview {
  title?: string;
  description?: string;
  completed?: string;
}
```

### `Record<Keys, Type>` – Constructs an object types whose property keys are `Keys` and whose property values are `Type`.
```ts
type TodoMap = Record<string, Todo>
// Sames as
interface TodoMap {
  [key: string]: Todo 
}
```

### `Pick<Type>` – Picks a set of properties.
```ts
type TodoPreview = Pick<Todo, "title" | "completed">;
// Same as
interface TodoPreview {
  title: string;
  completed: string;
}
```
### `Return<Type>` – Constructs a type consisting of the return type of function `Type`.
```ts
function updateTodo(todo: Todo, fieldsToUpdate: Partial<Todo>) {
  return { ...todo, ...fieldsToUpdate };
}

type UpdatedTodo = ReturnType<typeof updateTodo> // === Todo
```

## Type Manipulation

### [Keyof Type Operator](https://www.typescriptlang.org/docs/handbook/2/keyof-types.html) – Takes an object type and produces a literal union of its keys.
```ts
type TodoProps = keyof Todo;
// Same as
type TodoProps = `title` | 'description' | 'completed'
```

### [Typeof Type Operator](https://www.typescriptlang.org/docs/handbook/2/typeof-types.html) – Refers to the type of a variable or property.
```ts
typeof updateTodo
// Equals to
declare function updateTodo(todo: Todo, fieldsToUpdate: Partial<Todo>): {
    title: string;
    description: string;
    completed: boolean;
};
```

### [Indexed Access Types](https://www.typescriptlang.org/docs/handbook/2/indexed-access-types.html) – Looks up a specific property on another type.
```ts
// Record key access
type Title = Todo['title'];
// Equals to
type Title = string;

// Array number access
type TodoList = Todo[];
type ListItem = TodoList[number];
// Equals to
type ListItem = Todo;
```

### [Template Literal Types](https://www.typescriptlang.org/docs/handbook/2/template-literal-types.html) – Allows for narrowing down the type to the exact value.
```ts
function pickColor(color: string)
// Narrowed version which only accept specific `color` values.
function pickColor(color: 'red' | 'green' | 'blue';)
```
