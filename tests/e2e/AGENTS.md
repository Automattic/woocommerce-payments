# E2E guidance

- Await Playwright's asynchronous assertions. Prefer retrying locator assertions such as `toBeVisible()` and `toBeChecked()` to one-shot state reads. Calling `isVisible()` and discarding its result is not an assertion.
- Helpers must fail clearly when none of their expected outcomes occurs. Do not swallow a timeout and rely on an unrelated later assertion to explain it.
- Prefer locators tied to meaning or stable identifiers over upstream tag names or incidental wording. Preserve the intended specificity: an error-path test must still distinguish the expected error from a different failure.
