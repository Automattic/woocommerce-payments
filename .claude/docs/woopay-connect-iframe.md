# WooPay Connect iframe

**Last updated:** 2026-04-03

Reference for the hidden WooPay Connect iframe system used for cross-domain communication between the merchant site and WooPay.

## Architecture

A hidden 0×0 iframe loads `{woopayHost}/connect/` on every page with the WooPay button (when first-party auth is enabled — essentially always). It uses WooPay's first-party cookies to identify the user without needing their email.

## Key Files

| File | Purpose |
|------|---------|
| `client/checkout/woopay/connect/woopay-connect.js` | Base class — iframe injection, postMessage send/receive, timeout handling |
| `client/checkout/woopay/connect/woopay-connect-iframe.js` | React component rendering the hidden iframe |
| `client/checkout/woopay/connect/user-connect.js` | User state queries (`getIsUserLoggedIn`, `getEncryptedData`) |
| `client/checkout/woopay/connect/session-connect.js` | Session management (`setRedirectSessionData`, `setPreemptiveSessionData`) |
| `client/checkout/woopay/connect/connect-utils.js` | Injection state tracking, postMessage timeout config |

## Adding a New Query

Follow the pattern in `user-connect.js`:

1. Add a listener placeholder in the constructor: `this.listeners.myNewCallback = () => {}`
2. Add an async method that calls `sendMessageAndListenWith({ action: 'myAction' }, 'myNewCallback')`
3. Add a `case` in `callbackFn()` for `'my_action_success'` that calls `this.listeners.myNewCallback(data.value)`
4. WooPay's `/connect/` endpoint must handle the new action and respond with `{ action: 'my_action_success', value: ... }`

Timeouts (default 5s) are handled automatically — the method rejects and the caller receives `null` or `false`.

5. **Clean up after use:** Each `new WoopayConnect()` subclass adds a `window.addEventListener('message', ...)` in the constructor. Call `userConnect.detachMessageListener()` after the query resolves to prevent listener leaks. In React `useEffect`, return it as the cleanup function:

```javascript
useEffect( () => {
    const userConnect = new WooPayUserConnect();
    userConnect.getPreferredPaymentMethod().then( ( card ) => {
        // handle result
        userConnect.detachMessageListener();
    } );
    return () => userConnect.detachMessageListener();
}, [] );
```

The iframe itself is deduplicated via `INJECTED_STATE` — only one is ever created regardless of how many Connect instances exist. But each instance adds its own message listener.

## Timing

The Connect iframe loads **after** the WooPay button renders:

1. PHP renders button placeholder → 2. JS renders React button (visible) → 3. useEffect injects Connect iframe → 4. iframe loads from WooPay server → 5. postMessage available

Any data from the Connect iframe arrives after the button is already visible. Use `localStorage` caching if instant rendering is needed on subsequent visits.

## Testing Modules That Import Connect Classes

Importing any `WoopayConnect` subclass (e.g., `WooPayUserConnect`) triggers iframe injection in the constructor, which calls `getConfig('woopayHost')`. In tests where `getConfig` is mocked to return `undefined`, this causes:

```
TypeError: Cannot read properties of undefined (reading 'startsWith')
```

**Fix:** Mock the Connect module in any test file that imports a module using it:

```javascript
jest.mock( 'wcpay/checkout/woopay/connect/user-connect', () => {
    return jest.fn().mockImplementation( () => ( {
        getPreferredPaymentMethod: jest.fn().mockResolvedValue( null ),
        isUserLoggedIn: jest.fn().mockResolvedValue( false ),
    } ) );
} );
```

## Security

- All messages verified via `event.origin.startsWith(getConfig('woopayHost'))`
- postMessage timeout prevents hanging on unresponsive iframes
- User identification via WooPay's first-party cookies (no email/PII crosses domains)
