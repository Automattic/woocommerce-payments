const { TestEnvironment } = require( 'jest-environment-puppeteer' );
require( 'jest-circus' );

class JestCustomEnvironment extends TestEnvironment {
	async teardown() {
		if ( this.global.page ) {
			// Wait a few seconds before tearing down the page so we
			// have time to take screenshots and handle other events
			await this.global.page.waitForTimeout( 1000 );
		}
		await super.teardown();
	}

	async handleTestEvent( event, state ) {
		if ( event.name === 'test_fn_failure' && this.global.page ) {
			const testDescription = state.currentlyRunningTest.name.replace(
				/[":<>\|*?]/g,
				''
			);

			await this.global.page.screenshot( {
				path: `./screenshots/${ testDescription }.png`,
				fullPage: true,
			} );
		}
	}
}

module.exports = JestCustomEnvironment;
