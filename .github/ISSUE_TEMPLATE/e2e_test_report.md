---
name: e2e test report
about: Report an e2e test failure.
title: ''
labels: ['category: e2e', 'needs triage']
assignees: ''

---

### Description
<!-- Before reporting an issue, are you sure this e2e test is failing consistently? -->
<!-- Add a clear and concise description of what the issue is. Please, be as descriptive as possible. -->
<!-- Make sure you include relevant details such as what test/spec is failing and the link of the job where it started failing. -->

**Output from the test failure**:
<!-- Chunk of the output from the test failure. It doesn't need to be the entire output, just the relevant part. e.g.
    TimeoutError: waiting for selector "..." failed: timeout 100000ms exceeded
      336 | 	);
    > 337 | 	const selector = await page.waitForSelector(
          | 	                              ^
      339 | 			'selector'
 -->

### Additional context
<!-- Any additional context or details you think might be helpful. -->
<!-- Ticket numbers/links, plugin versions, system statuses etc. -->

### Priority
<!-- Add a priority label based on your best judgement (quick is fine). -->
<!-- Optional: add comments here to explain your decision or highlight any questions/potential risks. -->

### Reason why this e2e test is broken
<!-- Add one of the "e2e: broken flow/test/environment" labels based on your best judgement (quick is fine). -->
<!-- Optional: add comments here to explain your decision. -->

> [!Important]
> Please, ensure when closing this issue (PR fix) that only one `e2e: broken` label is added and it is accurate.
> - [ ] I confirmed there's only one `e2e: broken` label in this issue and it is accurate.
<!-- Leave the above for who's fixing this issue. -->
