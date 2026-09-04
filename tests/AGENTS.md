# Test guidance

The root [regression-test guidance](../AGENTS.md#regression-tests-and-verification) also applies to JavaScript tests under `client/`.

## PHP tests

- The `src` coverage gate is 100%; see [the CI coverage runner](../bin/run-ci-tests-check-coverage.bash). When removing a feature or its tests, check retained base implementations and shared helpers, including methods overridden by surviving test doubles.
- Do not define process-wide PHP constants for individual test scenarios: teardown cannot undefine them. Where production code uses a resettable wrapper such as `Automattic\Jetpack\Constants`, set and clear values through that wrapper. Check existing wrappers before adding a production override solely for tests.
- Restore changed hooks, globals, options, and wrapper state in teardown so later tests do not inherit the scenario. Populate the actual input the code reads; setting `$_GET` does not update `$_REQUEST` in a unit test.
