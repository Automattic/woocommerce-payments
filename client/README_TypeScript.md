## Suggested approach (WIP)

* Higher level data objects that come from store should have type definition within `client/data` directory.
  * If object has separate subdirectory type definitions goes in `client/data/subdirectory/definitions.d.ts`
    * E: `client/data/disputes/definitions.d.ts` in this PR
  * High level object type can contain other higher or lower level object types
    * High level types should be imported from its corresponding definition
      * E: `Dispute` has `Charge` as type for `charge` property. `import type Charge from 'client/data/charge/definitions'; export type Dispute = { charge: Charge }`
      * E: Both `Dispute` and `Transaction` have `Order` from `@woocommerce/api` as type for `order` property
      * E: Same example, if we wanted our own definition for Order it should go in `client/data/definitions.d.ts`
    * Other types should be defined within file but not exported. If at some point we'll need that type, it can easily be exported
      * E: `Evidence` type in `client/data/disputes/definitions.d.ts` in this PR
* Local scoped object types should be defined locally
  * Q: Inline in same file or separate `declarations.d.js` withing corresponding directory
* Any non-high-level type definition that should (Q: or could) be shared across files should be defined where it's originating.
    * Example: useDispute returns `{ dispute: Dispute; isLoading: boolean; doAccept: () => void; }` and type for this return object should be defined next to the method and imported together with method when used 
