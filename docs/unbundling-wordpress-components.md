# Unbundling WordPress Components

This document describes our strategy for progressively migrating from bundled `@wordpress/components` to WordPress core components. The goal is to reduce bundle size and maintain consistent styling across different screens.

## Why Unbundle?

1. **Bundle Size Reduction**
   - WordPress components are already available in the WordPress installation
   - Bundling them increases our plugin size unnecessarily
   - Reduces load time and improves performance

2. **Consistent Styling**
   - WordPress core components maintain consistent styling
   - Reduces the need for custom CSS overrides
   - Better integration with WordPress admin UI

3. **Maintenance**
   - Less code to maintain
   - Automatic updates when WordPress components are updated
   - Reduced risk of version mismatches

## How It Works

1. **Webpack Configuration**
   ```json
   ...
	externals: [
		/**
		 * Only externalize `@wordpress/components` when it's
		 * imported from client/payment-details/dispute-details/**
		 */
		function ( { context, request }, callback ) {
			if ( request === '@wordpress/components' && context ) {
				switch ( true ) {
					case context.includes(
						path.join(
							'client',
							'payment-details',
							'dispute-details'
						)
					):
					case context.includes( path.join( 'client', 'disputes' ) ):
						return callback( null, 'wp.components' );
					default:
						return callback();
				}
			}
			// Otherwise bundle normally
			callback();
		},
	],
    ...
   ```
   This configuration tells webpack to:
     - Use WordPress core components for specific paths
     - Bundle components for other paths
     - Allows gradual migration path by path

   **Important**: This path-based approach means:
     - Only components used within the specified path will use WordPress core components
     - Components created in the path but used elsewhere will still use bundled versions
     - The entire feature/page should be migrated together to ensure consistent behavior
     - Choose paths that represent complete, isolated features (e.g., entire pages or self-contained features)

    **Note**: For Disputes, this configuration was redundant since we are already passing the context per page. But for other cases where we need to externalize components to specific paths, this configuration is useful.

2. **Component Wrapping System**
   We use a `makeWrappedComponent` utility that creates wrapped components:
   ```typescript
   // makeWrappedComponent utility
   export const makeWrappedComponent = <T extends React.ComponentType<any>, N extends string>(
     BundledComponent: T,
     componentName: N
   ) =>
     React.forwardRef<any, ComponentProps<T> & { useBundledComponent?: boolean }>(
       (props, ref) => {
         const { useBundledComponent, ...rest } = props;
         const context = useContext(WordPressComponentsContext);

         if (!context || useBundledComponent) {
           return <BundledComponent {...rest} ref={ref} />;
         }

         const ContextComponent = context[componentName as keyof typeof context];
         return <ContextComponent {...rest} ref={ref} />;
       }
     );
   ```
   This system:
   - Provides runtime flexibility between bundled and context components
   - Maintains backward compatibility with `useBundledComponent` prop
   - Allows gradual migration
   - Properly forwards refs to underlying components
   - Uses TypeScript generics for type safety

   **Using Wrapped Components**
   We've already wrapped common WordPress components in `client/components/wp-components-wrapped/`. Each component is exported as an individual file using a shared `makeWrappedComponent` utility. This approach enables Webpack's tree-shaking mechanism to work more effectively, allowing consumers to import only the specific components they need.

   ```diff
   // Before
   - import { Button, Card, Notice } from '@wordpress/components';
   
   // Import individual components for better tree-shaking
   + import { Button } from 'wcpay/components/wp-components-wrapped/components/button';
   + import { Card } from 'wcpay/components/wp-components-wrapped/components/card';
   + import { Notice } from 'wcpay/components/wp-components-wrapped/components/notice';
   
   // Or import from the main index file
   + import { Button, Card, Notice } from 'wcpay/components/wp-components-wrapped';
   
   // The components will automatically handle the context switching
   const MyComponent = () => {
       return (
           <Card>
               <Notice>Hello</Notice>
               <Button>Click me</Button>
           </Card>
       );
   };
   ```

   Available wrapped components:
   - `Button`
   - `Card`, `CardBody`, `CardHeader`, `CardFooter`, `CardDivider`
   - `Notice`
   - `Flex`, `FlexItem`
   - `Icon`
   - `Modal`
   - `DropdownMenu`, `MenuGroup`, `MenuItem`
   - And more...

3. **Fine-grained Control with useBundledComponent**
   Sometimes you need more control over which version of a component to use, even within the same context. This is particularly useful when:
   - A component needs to behave differently in different contexts
   - You need to ensure consistent behavior in modals or popups
   - You're transitioning a feature and need to test both versions
   - You need to maintain backward compatibility during migration

   ```typescript
   const MyFeature = () => {
       const [showModal, setShowModal] = useState(false);
       const shouldUseBundled = useMemo(() => {
           // Add your logic here to determine which version to use
           // For example:
           // - Check if we're in a modal context
           // - Check if we need specific bundled features
           // - Check if we're in a transition period
           // *useMemo is totally optional here, it's just an example
           return showModal || someOtherCondition;
       }, [showModal, someOtherCondition]);
       
       return (
           <div>
               {/* Use WordPress version for the main UI */}
               <Button onClick={() => setShowModal(true)}>
                   Open Modal
               </Button>
               
               {/* Use bundled version based on logic */}
               {showModal && (
                   <Modal
                       useBundledComponent={shouldUseBundled}
                       onRequestClose={() => setShowModal(false)}
                   >
                       <ComplexForm />
                   </Modal>
               )}
           </div>
       );
   };
   ```

   You can control the `useBundledComponent` prop with:
   - Boolean values for simple cases
   - Logic based on component context
   - Feature flags for gradual rollout
   - Environment variables for different deployments
   - User preferences or settings

## How to Migrate a Component

1. **Identify Migration Scope**
   - Can the entire directory be migrated?
     - If yes, use webpack configuration (quickest solution)
     - If no, proceed with individual component migration
   - Example: Dispute details page was a good candidate for webpack configuration as it's a self-contained feature

2. **Add Context to Routes**
   - For individual component migration, add the route to `client/index.js`
   - This is the most common approach as it allows gradual migration
   ```diff
       // client/index.js
 		pages.push( {
   -      container: MyPage,
   +      container: ( { query } ) => (
   +        <WordPressComponentsContext.Provider value={ wp.components }>
   +          <MyPage query={ query } />
   +        </WordPressComponentsContext.Provider>
   +      ),
          path: '/my-feature',
          ...
       },
   ```

3. **Use Wrapped Components**
   - Check if the component exists in `client/components/wp-components-wrapped/index.tsx`
   - If it exists, simply update the import path:
   ```diff
   - import { Button, Card, Notice } from '@wordpress/components';
   + import { Button, Card, Notice } from 'wcpay/components/wp-components-wrapped';
   ```
   - If it doesn't exist, add it to the wrapped components:
   ```typescript
   // client/components/wp-components-wrapped/components/new-component.tsx
   import { NewComponent as BundledNewComponent } from '@wordpress/components';
   import { makeWrappedComponent } from '../make-wrapped-component';
   
   export const NewComponent = makeWrappedComponent( BundledNewComponent, 'NewComponent' );
   ```
   
   - Then export it from the main index file:
   ```typescript
   // client/components/wp-components-wrapped/index.tsx
   export { NewComponent } from './components/new-component';
   ```

4. **Wrap Custom Components**
   - For components that use WordPress components internally (like `client/settings/card-body/index.tsx`)
   - Implement the wrapping strategy:
   ```typescript
   const MyComponent = (props) => {
     const { useBundledComponent, ...rest } = props;
     const context = useContext(WordPressComponentsContext);
     if (!context || useBundledComponent) {
       return <BundledComponent {...rest} />;
     }
     const { Component } = context;
     return <Component {...rest} />;
   };
   ```

5. **Handle Shared Context**
   - For components that share context (like `client/payment-details/timeline/index.js`)
   - Use fine-grained control with `useBundledComponent`:
   ```typescript
   const SharedComponent = (props) => {
     const { useBundledComponent, ...rest } = props;
     const shouldUseBundled = useMemo(() => {
       // Add logic to determine which version to use
       return someCondition;
     }, [someCondition]);
     
     return (
       <Component
         useBundledComponent={shouldUseBundled}
         {...rest}
       />
     );
   };
   ```

6. **Update Documentation**
   ```diff
   ## Migration Status
   - [x] Dispute Details
   + - [ ] New Path
   +   - [x] NewComponent
   +   - [ ] OtherComponent
   ```

7. **Update webpack configuration (Quick Path Migration)**
   If you're migrating an entire page or feature at once, you can use the webpack configuration to externalize all components in that path. This is the quickest solution when:
   - The entire page/feature is ready for migration
   - All components are contained within the same path
   - No shared components are affected
   
   ```diff
   externals: [
       function ( { context, request }, callback ) {
           if ( request === '@wordpress/components' && context ) {
               switch ( true ) {
   +                // Add new path to externalize
   +                case context.includes(
   +                    path.join( 'client', 'new-feature-path' )
   +                ):
                   // Existing paths
                   case context.includes(
                       path.join( 'client', 'payment-details', 'dispute-details' )
                   ):
                       return callback( null, 'wp.components' );
                   default:
                       return callback();
               }
           }
           callback();
       },
   ]
   ```

## Real Example: Dispute Details Migration

The dispute details area was chosen as a migration target because:
- It's a relatively isolated feature
- Has clear boundaries
- Relatively small scope

However, it's important to note that while it appears self-contained, it actually shares context with the payment details area. This required a mixed approach:

1. **Path-based Migration with Exceptions**
   ```javascript
   externals: [
       function ( { context, request }, callback ) {
           if ( request === '@wordpress/components' && context ) {
               switch ( true ) {
                   case context.includes(
                       path.join( 'client', 'payment-details', 'dispute-details' )
                   ):
                       return callback( null, 'wp.components' );
                   default:
                       return callback();
               }
           }
           callback();
       },
   ]
   ```

2. **Fine-grained Control for Shared Components**
   ```typescript
   // dispute-resolution-footer.tsx
   const DisputeResolutionFooter = ({ dispute }) => {
       const [showModal, setShowModal] = useState(false);
       
       return (
           <div>
               {/* Use WordPress version for the main UI */}
               <Button onClick={() => setShowModal(true)}>
                   Submit Evidence
               </Button>
               
               {/* Use bundled version in the modal to ensure consistent behavior */}
               {showModal && (
                   <Modal
                       useBundledComponent
                       onRequestClose={() => setShowModal(false)}
                   >
                       <EvidenceForm dispute={dispute} />
                   </Modal>
               )}
           </div>
       );
   };
   ```

3. **Context Sharing Considerations**
   - Components in the dispute details area share context with payment details
   - Some components need to work in both contexts
   - Used `useBundledComponent` for components that need to work in both contexts
   - Example: Timeline components that appear in both payment and dispute views

## Migration Checklist

✅ Completed Migrations:
1. Dispute Details
   - Path: `client/payment-details/dispute-details`
   - Components:
     - `DisputeNotice`
     - `DisputeSteps`
     - `DisputeDueByDate`
     - `DisputeResolutionFooter`
     - `EvidenceList`
     - `DisputeSummaryRow`
   - Date: 2024-03-20
   - Status: Completed

🔄 In Progress:
1. New Feature Path
   - Path: `client/new-feature-path`
   - Components:
     - `NewComponent`
   - Date: 2024-03-21
   - Status: In Progress
   - Current Phase: Testing
