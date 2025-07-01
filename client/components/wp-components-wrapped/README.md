# WordPress Components "Wrapped"

The `wp-components-wrapped` directory contains individual component files that wrap `@wordpress/components` to ease the transition towards de-bundling the WordPress components package.

## Tree-Shaking Optimization

Each component is exported as an individual file using a shared `makeWrappedComponent` utility. This approach enables Webpack's tree-shaking mechanism to work more effectively, allowing consumers to import only the specific components they need without importing the entire bundled `@wordpress/components` package.

## Usage

```typescript
// Import individual components for better tree-shaking
import { Button } from 'wcpay/components/wp-components-wrapped/components/button';
import { Card } from 'wcpay/components/wp-components-wrapped/components/card';

// Or import from the main index file
import { Button, Card } from 'wcpay/components/wp-components-wrapped';
```

This structure reduces bundle size by eliminating unused `@wordpress/components` from the final build.
