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

**Output from the test failure**:
<!-- Chunk of the output from the test failure. It doesn't need to be the entire output, just the relevant part. e.g.
    TimeoutError: waiting for selector "..." failed: timeout 100000ms exceeded
      336 | 	);
    > 337 | 	const selector = await page.waitForSelector(
          | 	                              ^
      339 | 			selector
 -->

**GitHub jobs where this spec is failing**:
<!-- Can you list all the jobs where the test failing? e.g. "WC - latest | wcpay - shopper", "WC - beta | wcpay - shopper" etc. -->
- 

### Additional context
<!-- Any additional context or details you think might be helpful. -->
<!-- Ticket numbers/links, plugin versions, system statuses etc. -->

---

- [ ] Added priority label.
<!-- For the priority label, use your best judgement. -->
- [ ] Added e2e label.
<!-- Add one of the "e2e: broken flow/test/environment" labels. See their description and try to match it with the actual issue. -->
