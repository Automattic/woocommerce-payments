import { expect } from '@playwright/test';
import axios from 'axios';
import fs from 'fs';
import path from 'path';

const {
    QIT_SITE_URL,
    QIT_DOMAIN,
    QIT_INTERNAL_DOMAIN,
    QIT_INTERNAL_NGINX,
    FORCE_COLOR
} = process.env;

let _cachedDomain = null;

const forceColorEnv = (FORCE_COLOR || '').toLowerCase();
const forcedOff = forceColorEnv === '0' || forceColorEnv === 'false';
const forcedOn  = forceColorEnv && !forcedOff;
const canColor  = forcedOn || (!forcedOff && process.stdout.isTTY);

/**
 * Makes an HTTP or HTTPS POST request using Axios with automatic redirect handling.
 * @param {string} hostname - The hostname or full URL of the server.
 * @param {string} path - The path of the endpoint.
 * @param {Object} data - The data to send in the POST request.
 * @returns {Promise<Object>} - Resolves with the response object.
 */
async function makePostRequest(hostname, path, data) {
    try {
        let finalHost = hostname;
        if (!finalHost) {
            finalHost = await getDomain();
        }

        // Minimal check for protocol
        const hasProtocol = finalHost.startsWith('http://') || finalHost.startsWith('https://');
        const url = hasProtocol ? `${finalHost}${path}` : `http://${finalHost}${path}`;

        const response = await axios.post(url, data, {
            headers: { 'Content-Type': 'application/json' },
            maxRedirects: 5,
            timeout: 600000, // 10 min
            validateStatus: () => true, // accept any status
        });

        return {
            status: response.status,
            body: response.data,
        };
    } catch (error) {
        console.error('[qit] Request Error:', error.message);
        throw error;
    }
}

/**
 * Attempts to figure out a working domain by:
 * 1) Checking QIT_SITE_URL first (new env variable for test packages)
 * 2) Checking QIT_DOMAIN,
 * 3) If that fails, checking QIT_INTERNAL_DOMAIN,
 * 4) Caches whichever works so we don't keep retesting.
 */
async function getDomain() {
    // If we already found a working domain, return it.
    if (_cachedDomain) {
        return _cachedDomain;
    }

    // Helper inline: tries GET /wp-json on the given domain, returns true if OK, otherwise throws
    async function ping(domain) {
        const hasProtocol = domain.startsWith('http://') || domain.startsWith('https://');
        const urlBase = hasProtocol ? domain : `http://${domain}`;
        const checkUrl = `${urlBase}/wp-json/`;
        const resp = await axios.get(checkUrl, { timeout: 3000 }); // 3s
        if (resp.status !== 200) {
            throw new Error(`Ping got non-200 status: ${resp.status}`);
        }
    }

    // 0) Try QIT_SITE_URL if set (preferred for test packages)
    if (QIT_SITE_URL) {
        try {
            await ping(QIT_SITE_URL);
            _cachedDomain = QIT_SITE_URL; // it worked, cache it
            return _cachedDomain;
        } catch (err) {
            console.warn(
                `[qit] QIT_SITE_URL ("${QIT_SITE_URL}") failed to respond; falling back to other domains. Reason:`,
                err.message
            );
        }
    }

    // 1) Try QIT_DOMAIN if set
    if (QIT_DOMAIN) {
        try {
            await ping(QIT_DOMAIN);
            _cachedDomain = QIT_DOMAIN; // it worked, cache it
            return _cachedDomain;
        } catch (err) {
            console.warn(
                `[qit] QIT_DOMAIN ("${QIT_DOMAIN}") failed to respond; falling back to QIT_INTERNAL_DOMAIN. Reason:`,
                err.message
            );
        }
    }

    // 2) If QIT_DOMAIN missing or failed, try QIT_INTERNAL_DOMAIN
    if (QIT_INTERNAL_DOMAIN) {
        try {
            await ping(QIT_INTERNAL_DOMAIN);
            _cachedDomain = QIT_INTERNAL_DOMAIN; // success, cache it
            return _cachedDomain;
        } catch (err) {
            console.warn(
                `[qit] QIT_INTERNAL_DOMAIN ("${QIT_INTERNAL_DOMAIN}") failed to respond. Reason:`,
                err.message
            );
        }
    } else {
        console.warn('[qit] No QIT_INTERNAL_DOMAIN set.');
    }


    // 3) Test QIT_INTERNAL_NGINX
    if (QIT_INTERNAL_NGINX) {
        try {
            await ping(QIT_INTERNAL_NGINX);
            _cachedDomain = QIT_INTERNAL_NGINX; // success, cache it
            return _cachedDomain;
        } catch (err) {
            console.warn(
                `[qit] QIT_INTERNAL_NGINX ("${QIT_INTERNAL_NGINX}") failed to respond. Reason:`,
                err.message
            );
        }
    } else {
        console.warn('[qit] No QIT_INTERNAL_NGINX set.');
    }

    // 4) If we get here, nothing worked
    throw new Error('No working domain found in QIT_SITE_URL, QIT_DOMAIN, QIT_INTERNAL_DOMAIN, or QIT_INTERNAL_NGINX.');
}

const qit = {
    activeBrowser: null,
    verbose: false, // Todo: Make this configurable from "-v" or "-vvv"
    canColor,
    async loginAsAdmin(page) {
        // Check if the admin cookies are already set.
        const adminCookies = qit.getEnv('admin_cookies');
        let usedCookies = false;

        if (adminCookies && Object.keys(adminCookies).length > 0) {
            const cookies = JSON.parse(adminCookies);
            await page.context().addCookies(cookies);
            usedCookies = true;
        }

        // Navigate to wp-admin to ensure we're on the admin dashboard.
        await page.goto('/wp-admin/');
        await page.waitForLoadState('networkidle');

        try {
            if (!usedCookies) {
                throw new Error('Admin cookies not found');
            }

            // Check if the "Dashboard" heading is visible.
            await expect(page.getByRole('heading', {name: 'Dashboard'})).toBeVisible();
        } catch (error) {
            // Regardless of whether cookies were used, clear cookies and perform the login flow again.
            await page.context().clearCookies(); // Clear existing cookies.
            await page.goto('about:blank'); // Reset the page.

            // Perform the login flow.
            await this._performLoginFlow(page, 'admin', 'password', true);

            // Re-check if the "Dashboard" heading is visible.
            await page.goto('/wp-admin/');
            await page.waitForLoadState('networkidle');
            await expect(page.getByRole('heading', {name: 'Dashboard'})).toBeVisible();
        }
    },
    // Define the login flow as a separate function for reuse.
    async _performLoginFlow(page, username, password, saveCookies = false) {
        await page.goto('/wp-admin/');
        await page.getByLabel('Username or Email Address').fill(username);
        await page.getByLabel('Password', {exact: true}).fill(password);
        await page.getByRole('button', {name: 'Log In'}).click();
        await page.waitForLoadState('networkidle');

        if (saveCookies) {
            // Save the new cookies.
            const cookies = await page.context().cookies();
            qit.setEnv('admin_cookies', JSON.stringify(cookies));
        }
    },
    async loginAs(page, username, password) {
        await this._performLoginFlow(page, username, password);

        // Optionally navigate to the dashboard or a specific page.
        await page.goto('/wp-admin/');
        await page.waitForLoadState('networkidle');
    },
    async individualLogging(action) {
        // Internal function. "action" can be either "start" or "stop".
        try {
            const response = await makePostRequest(await getDomain(), '/wp-json/qit/v1/individual-log', {qit_individual_log: action});

            if (response.body && typeof response.body.output !== 'undefined') {
                return response.body.output;
            } else {
                console.error('Invalid or missing "output" in response body:', response.body);
                throw new Error('Invalid response: "output" not found');
            }
        } catch (error) {
            console.error('Error making POST request:', error);
            throw error;
        }
    },
    async runStreamedCommand(command) {
        const domain = await getDomain();
        const url = `http://${domain}/wp-json/qit/v1/exec/stream`;

        if (this.verbose) {
            console.log(`[Node] About to stream command: ${command}`);
        }

        return new Promise(async (resolve, reject) => {
            let allOutput = '';
            let exitCode  = 1; // Default until found
            let leftover  = '';

            // Helper to process a single line of text
            const processLine = (line) => {
                // 1) Always append the raw line (plus newline) to allOutput
                allOutput += line + '\n';

                // 2) See if there's an exit marker in the line
                const exitMatch = line.match(/__QIT_STREAM_EXIT__CODE__START__(\d+)__END/);
                if (exitMatch) {
                    exitCode = parseInt(exitMatch[1], 10);
                    // remove placeholder
                    line = line.replace(/__QIT_STREAM_EXIT__CODE__START__(\d+)__END/, '');
                }

                // 3) Parse out any __QIT_STDOUT__ / __QIT_STDERR__ sections
                const segments = line.split(/(__QIT_STDOUT__|__QIT_STDERR__)/);

                let mode = 'normal';
                for (let seg of segments) {
                    if (seg === '__QIT_STDOUT__') {
                        mode = 'stdout';
                        continue;
                    }
                    if (seg === '__QIT_STDERR__') {
                        mode = 'stderr';
                        continue;
                    }
                    if (!seg) {
                        continue;
                    }

                    // 4) Print depending on mode
                    if (mode === 'stderr') {
                        if (canColor) {
                            console.error(`\x1b[33m${seg}\x1b[0m`); // Yellow
                        } else {
                            console.error(seg);
                        }
                    } else {
                        // Normal text
                        console.log(seg);
                    }
                }
            };

            try {
                const response = await axios.post(
                    url,
                    { qit_command: command, verbose: this.verbose },
                    {
                        responseType: 'stream',
                        timeout: 10 * 60 * 1000,            // 10 minutes
                        maxBodyLength: 100 * 1024 * 1024,   // 100 MB
                        maxContentLength: 100 * 1024 * 1024,
                        validateStatus: () => true,         // accept all statuses
                    }
                );

                // Handle streamed data
                response.data.on('data', (chunk) => {
                    // Convert to string and prepend leftover from prior chunk
                    let text = leftover + chunk.toString();
                    leftover = '';

                    // Split by newline
                    const lines = text.split('\n');

                    // If the last chunk didn't end with a newline, hold it for next event
                    if (!text.endsWith('\n')) {
                        leftover = lines.pop();
                    }

                    // Process each complete line
                    for (const line of lines) {
                        processLine(line);
                    }
                });

                response.data.on('end', () => {
                    // If there's a leftover partial line, process it one last time
                    if (leftover) {
                        processLine(leftover);
                        leftover = '';
                    }

                    if (this.verbose) {
                        console.log('[Node] Streaming ended.\n');
                    }

                    // Resolve with final code & full logs
                    resolve({ status: exitCode, output: allOutput });
                });

                response.data.on('error', (err) => {
                    console.error('[Node] Streaming error:', err);
                    reject(err);
                });

            } catch (outerErr) {
                console.error(`[Node] Error starting streaming request: ${outerErr.message}`);
                reject(outerErr);
            }
        });
    },
    /**
     * Attaches a screenshot to the test context.
     *
     * @param {string} name - The name of the screenshot.
     * @param {Object.<string, string[]>} context - An array where keys are strings, and values are a flat array of strings.
     * @param {import('playwright').Page} page - The Playwright page object.
     * @param {import('playwright').TestInfo} testInfo - The Playwright test info object.
     * @returns {Promise<void>}
     */
    async attachScreenshot(name, context, page, testInfo) {
        // Use relative path for test media (Playwright runs on host, not in container)
        const testMediaDir = path.join(process.cwd(), 'test-media');

        if (!fs.existsSync(testMediaDir)) {
            fs.mkdirSync(testMediaDir, {recursive: true});
        }

        const safeName = name.replace(/[^a-zA-Z0-9-]/g, '_');
        const basename = `${safeName}-${Date.now()}`;

        const screenshotPath = path.join(testMediaDir, `${basename}.jpg`);

        // Write "context" as a JSON file with the same name.
        const contextPath = path.join(testMediaDir, `${basename}.json`);
        fs.writeFileSync(contextPath, JSON.stringify(context, null, 2), 'utf8');

        try {
            await page.screenshot({path: screenshotPath, type: 'jpeg', fullPage: true, timeout: 90000});
            await testInfo.attach(safeName, {path: screenshotPath});
        } catch (error) {
            console.error('Error capturing or attaching screenshot:', error);
        }
    },
    async exec(command, silent = false) {
        const response = await makePostRequest(await getDomain(), '/wp-json/qit/v1/exec', {
            qit_command: command
        });

        if (!silent) {
            console.log(response.body.output);
        }

        // Check if something we expect is undefined
        if (typeof response.body.status === 'undefined' || typeof response.body.stdout === 'undefined' || typeof response.body.stderr === 'undefined') {
            console.error(command);
            console.error(response);
            throw new Error('Invalid response: "status", "stdout" or "stderr" not found.');
        }

        if (response.body.status !== 0) {
            // Command failed; throw an error with the output
            throw new Error(`Command failed with status ${response.body.status}: ${response.body.stdout} ${response.body.stderr}`);
        }

        return {
            status: response.body.status,
            stdout: response.body.stdout,
            stderr: response.body.stderr,
        };
    },
    // Execute "wp" CLI commands asynchronously. It takes everything after "wp" as a parameter
    // and executes it. Returns the output as a string. Throws an error if the command execution fails.
    async wp(command, silent = true) {
        // If command doesn't start with 'wp', add it
        const wpCommand = command.trim().startsWith('wp ') ? command : `wp ${command}`;
        return this.exec(wpCommand, silent);
    },
    getEnv(key = null) {
        try {
            const filePath = '/tmp/qit_env_helper.json';
            const data = fs.readFileSync(filePath, 'utf8');
            const envs = JSON.parse(data);

            if (key) {
                return envs[key];
            } else {
                return envs;
            }
        } catch (error) {
            return {};
        }
    },
    setEnv(key, value) {
        if (typeof key !== 'string' || typeof value !== 'string') {
            throw new Error('Key and value must be strings');
        }

        try {
            const filePath = '/tmp/qit_env_helper.json';
            let envs = {};

            try {
                const data = fs.readFileSync(filePath, 'utf8'); // synchronous read
                envs = JSON.parse(data);
            } catch (error) {
                if (error.code !== 'ENOENT') {
                    throw error;
                }
            }

            envs[key] = value;
            fs.writeFileSync(filePath, JSON.stringify(envs, null, 2), 'utf8'); // synchronous write
        } catch (error) {
            console.error(`Error writing to the environment file: ${error.message}`);
            throw error;
        }
    },
    unsetEnv(key) {
        if (typeof key !== 'string') {
            throw new Error('Key must be a string');
        }

        try {
            const filePath = '/tmp/qit_env_helper.json';
            let envs = {};

            try {
                const data = fs.readFileSync(filePath, 'utf8'); // Synchronous read
                envs = JSON.parse(data);
            } catch (error) {
                if (error.code !== 'ENOENT') {
                    throw error;
                }
                // If file doesn't exist, envs remains an empty object
            }

            delete envs[key];

            fs.writeFileSync(filePath, JSON.stringify(envs, null, 2), 'utf8'); // Synchronous write
        } catch (error) {
            console.error(`Error updating the environment file: ${error.message}`);
            throw error;
        }
    }
};

export default qit;