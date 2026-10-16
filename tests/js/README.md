# WooPayments JavaScript Unit Tests

Jest + React Testing Library tests for the `client/` frontend. Tests are co-located with the source they cover, in `__tests__/` directories. Configuration lives in [`tests/js/jest.config.js`](jest.config.js).

For PHP unit tests, see [tests/unit/README.md](../unit/README.md). For an overview of all test suites, see [tests/README.md](../README.md).

## Running the tests

From the plugin root directory:

```bash
pnpm run test:js                 # Run all JS unit tests
pnpm run test:watch              # Watch mode (re-runs on change)
pnpm run test:debug              # Debug mode (attach an inspector)
pnpm run test:update-snapshots   # Update Jest snapshots
```

To run a single file or match tests by name, pass Jest arguments through:

```bash
pnpm run test:js -- path/to/file.test.tsx
pnpm run test:js -- -t "renders the deposit schedule"
```
