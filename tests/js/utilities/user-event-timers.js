/**
 * External dependencies
 */
import userEvent from '@testing-library/user-event';

const user = userEvent.setup( { advanceTimers: jest.advanceTimersByTime } );

export { user as userEvent };
