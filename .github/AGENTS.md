# CI guidance

- For cache changes, trace restore, setup cleanup, container mounts, consumption, and save. Keep cached inputs outside directories that setup deletes, and verify both a cache miss and a subsequent hit in job logs.
- For sharding changes, preserve environment isolation, unique artifact names, and the intended test selection on both the initial run and retries. Check that the combined shards cover the original suite.
- Update [the test matrix reference](../docs/test-matrix.md) when changing workflow coverage, triggers, or required/non-blocking status. Inspect branch protection before describing a check as required.
