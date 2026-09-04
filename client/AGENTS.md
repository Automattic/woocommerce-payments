# Client guidance

## Request state

- A request finishing does not prove its data exists. Distinguish loading, failure, missing fields, and successful empty results. Cover the relevant missing/error state in regression tests.
- Preserve meaningful `false`, `0`, and `null` values. An explicit server decision must not activate a fallback intended for an absent field.
- Missing optional display data should not crash the surrounding page. Give unknown external enum values safe display behavior; do not invent a payment decision to keep rendering.

## Upstream components and rendered UI

- Inspect the component version actually loaded, its supported props, rendered elements, and stylesheet source. Do not rely on incidental upstream initialization or reproduce private implementation details. Remove obsolete integration work when no supported upstream consumer uses it.
- Reusing a nearby pattern does not establish that its composed HTML is accessible. Avoid nested interactive controls and preserve keyboard and touch access when changing tooltip wrappers.
- Scope necessary CSS overrides to the feature. When addressing an upstream compatibility change, check the affected UI on versions before and after that change.
- Compare rendered copy with the agreed design. Verify that a help link's destination section answers the adjacent text; a successful HTTP response or matching snapshot does not establish that the guidance is correct.
