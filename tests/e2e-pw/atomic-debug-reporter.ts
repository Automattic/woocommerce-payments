/* eslint-disable no-console */
/**
 * External dependencies
 */
import type {
	FullConfig,
	FullResult,
	Reporter,
	Suite,
	TestCase,
	TestResult,
} from '@playwright/test/reporter';

class AtomicDebugReporter implements Reporter {
	onBegin( config: FullConfig, suite: Suite ) {
		console.log(
			`Starting the run with ${ suite.allTests().length } tests`
		);
	}

	onTestBegin( test: TestCase ) {
		console.log( `Starting test ${ test.title }` );
	}

	onTestEnd( test: TestCase, result: TestResult ) {
		console.log( `Finished test ${ test.title }: ${ result.status }` );

		if ( result.status === 'failed' || result.status === 'timedOut' ) {
			console.log( 'First error thrown during test execution:' );
			console.log( result.error.message );
			console.log( result.error.snippet );
			console.log( result.error.stack );
		}
	}

	onEnd( result: FullResult ) {
		console.log( `Finished the run: ${ result.status }` );
	}
}

export default AtomicDebugReporter;
