/**
 * Creates a wrapper around a function that ensures a function can not be
 * called in rapid succesion. The function can only be executed once and then again after
 * the wait time has expired.  Even if the wrapper is called multiple times, the wrapped
 * function only excecutes once and then blocks until the wait time expires.
 *
 * @param {int} wait       Milliseconds wait for the next time a function can be executed.
 * @param {Function} func       The function to be wrapped.
 * @param {bool} immediate Overriding the wait time, will force the function to fire everytime.
 *
 * @return {Function} A wrapped function with execution limited by the wait time.
 */
const debounce = ( wait, func, immediate = false ) => {
	let timeout;
	return function () {
		const context = this,
			args = arguments;
		const later = () => {
			timeout = null;
			if ( ! immediate ) {
				func.apply( context, args );
			}
		};
		const callNow = immediate && ! timeout;
		clearTimeout( timeout );
		timeout = setTimeout( later, wait );
		if ( callNow ) {
			func.apply( context, args );
		}
	};
};

export default debounce;
