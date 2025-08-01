/**
 * Use this when using `@testing-library/user-event` in a test using `jest.useFakeTimers()`.
 * See https://testing-library.com/docs/user-event/options/#advancetimers for an explanation.
 */
/**
 * External dependencies
 */
import userEvent from '@testing-library/user-event';

const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );

export { user as userEvent };
