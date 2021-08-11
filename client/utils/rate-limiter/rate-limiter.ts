/**
 * Singleton in charge of managing rate limit payment attempts.
 * Get the instance with `getInstance()` public method.
 */
class RateLimiter {
	private static instance: RateLimiter;

	private cookieName = 'wc_payments_failed_transactions';

	private secondsPerFailure = 1;

	private numberTransactionsThreshold = 3;

	private cookieAgeInSeconds = 3600; // 1 hour

	private failedTransactions: FailedTransaction[] = [];

	private constructor() {
		this.getTransactionsFromCookie();
	}

	public static getInstance(): RateLimiter {
		if ( ! RateLimiter.instance ) {
			RateLimiter.instance = new RateLimiter();
		}

		return RateLimiter.instance;
	}

	private writeTransactionsToCookie() {
		document.cookie = `${ this.cookieName }=${ JSON.stringify(
			this.failedTransactions
		) };max-age=${ this.cookieAgeInSeconds };SameSite=Lax;path=/`;
	}

	private getTransactionsFromCookie() {
		const retrievedFailedTransactions = document.cookie
			.split( '; ' )
			.reduce( ( r, v ) => {
				const parts = v.split( '=' );
				return parts[ 0 ] === this.cookieName
					? decodeURIComponent( parts[ 1 ] )
					: r;
			}, '' );
		this.failedTransactions =
			'' !== retrievedFailedTransactions
				? JSON.parse( retrievedFailedTransactions )
				: [];
	}

	/**
	 * Adds a failed transaction into the private list.
	 *
	 * @param {FailedTransaction} transaction New failed transaction to add.
	 * @return {undefined}
	 */
	public addFailedTransaction( transaction: FailedTransaction ): void {
		this.failedTransactions.push( transaction );
		this.writeTransactionsToCookie();
	}

	/**
	 * Checks the defined thresholds and sleeps before continuing
	 * using await.
	 * Use this function before processing a checkout.
	 *
	 * @return {undefined}
	 */
	public async waitIfNeeded(): Promise< void > {
		if (
			this.failedTransactions.length >= this.numberTransactionsThreshold
		) {
			const timeToWait =
				this.failedTransactions.length * this.secondsPerFailure * 1000;
			await new Promise( ( r ) => setTimeout( r, timeToWait ) );
		}
	}
}

interface FailedTransaction {
	timestamp: number;
	code: string;
}

export default RateLimiter;
