# Main settings DataForm proof of concept

This experiment registers the main WooPayments settings page in Luigi's WooCommerce DataForm application. It is for comparing integration effort, not for release. The normal WooPayments settings page remains available.

## Requirements

- Use the companion WooCommerce branch `poc/settings-extension-entities`, based on `poc/dataform-exploration-compatibility-layer` at `adcb764232fbfac173f41344cb965800ec144c8f` (WooCommerce PR #68729).
- Use a WordPress runtime providing View Config and the POC's required script modules. The browser smoke test used WordPress 7.1.
- Enable the WooCommerce v4 REST API experiment and build its assets with `pnpm --filter @woocommerce/settings-dataform build:core` from the WooCommerce checkout.
- Build WooPayments assets with `pnpm run build:client`.
- Define `WCPAY_SETTINGS_DATAFORM_POC` as `true` in the local test site's configuration. Use a connected WooPayments test account.
- Open **WooCommerce > WooPayments (DataForm POC)**. The underlying URL is `admin.php?page=wc-settings-dataform-wp-admin&p=/settings/woopayments`.

Do not enable this experiment on a production store. Core's private field registry remains a dependency, and the new registration hooks are only a POC extension point.

## What changes

| Responsibility | Implementation |
| --- | --- |
| Page discovery and singleton entity | `client/settings-dataform/index.ts` registers the page and its REST URL with the companion POC hooks. |
| Controls | `client/settings-dataform/fields.tsx` supplies standard fields, the existing WooPayments payment-method/BNPL/express-checkout components and a payout summary. |
| Layout | `SettingsDataFormService` supplies PHP View Config with the main page's group order. |
| Editing, dirty state, Save and Discard | The shared WooCommerce application and WordPress core-data. |
| Saving | A flat-record facade delegates through the existing WooPayments REST route, including its permission checks, validation and side effects. |

When only the fraud protection level changes, the facade reads the current advanced rules and includes them in the delegated save: the existing endpoint requires both, while core-data sends only changed properties.

The facade is needed because the existing settings POST wraps its record in another REST response. After a successful save, it reads the authoritative settings record for core-data. A failed read after a successful write can still leave a partially completed operation; this is not a transactional save mechanism. The existing endpoint can also save local options before a later account update fails.

WooPayments does not call `unlock` itself. The companion Core POC passes its private field registration action to the integration. This still depends on the same unsupported API and is not a production-safe replacement for a public registration API.

## Reusing existing components

The custom field mounts the existing components under `RegistryProvider`. `settings-registry.js` exposes the settings store interface those components already use, but reads and edits the shared WordPress entity. The five setters used by these controls are explicitly adapted. Extending reuse to another component requires checking its selectors/actions, context and assets; this is not an automatic adapter for every WooPayments action or save flow.

`existing-controls.tsx` supplies the existing account and duplicate-notice contexts. Shared control components are extracted from the old payment-method, BNPL and express-checkout sections so the new DataForm cards own layout, headings and page width. The normal settings page wraps those same controls in its existing sections. No legacy two-column section or page-width override is mounted in the new page. Some existing TypeScript declarations were corrected when the typed wrapper exposed them.

This is a concrete component-reuse experiment. Importing real components preserves their implementation; it does not establish tested parity across every account state. No component should be replaced with a simplified control to make that comparison look smaller.

## Coverage and remaining migration work

- Edit enablement, test mode (outside onboarding and development mode), saved cards, manual capture, statement descriptors, support contact details, account notification email, multi-currency and debug logging (outside development mode).
- Reuse the controls extracted from `PaymentMethodsSection`, `BuyNowPayLaterSection` and `ExpressCheckout`, including their existing rows, logos, descriptions, fees, eligibility logic, activation dialogs, duplicate notices and customisation links. Their hooks run through a scoped registry adapter backed directly by the core-data entity. The adapter reuses the existing selectors, action creators and reducer; it does not maintain a second settings copy. Non-settings stores (such as payment-method promotions) remain inherited from the parent registry. Account metadata, payment-method definitions and styles come from the existing WooPayments providers.
- Keep a custom payout summary with a link to the existing editor. Fraud protection keeps its Basic/Advanced choices on the main page using standard radio controls, with a link to the existing separate advanced-rules screen.
- Keep express checkout customisation screens linked to the existing settings page. Subscription and Stripe Billing controls, bank-account editing, VAT collection, onboarding modals, tours, page-level promotions and detailed account-status restrictions are not migrated.
- Test mode and manual capture use standard checkboxes with explanatory text instead of confirmation dialogs. Changes take effect only after Save changes. Full existing explanatory UI and tracking are not reproduced.
- Server errors appear in the shared notice. Per-field server error placement and the existing phone-input formatting are not reproduced.
- The main group order is retained, but this is a separate POC route. Existing Woo settings tabs, breadcrumbs, section anchors and URLs are not replaced or fully reproduced.
- The companion POC warns on document unload with unsaved changes. In-app route blocking is not implemented.

This is a bounded main-page integration, not feature parity with the current settings page or a complete migration of the earlier modern-settings prototype. Its line count must not be presented as the cost of migrating all WooPayments settings.

## Verification

1. With the constant disabled, check that the normal WooPayments settings still render and the POC entry is absent.
2. Enable the constant and open the POC. Check the main groups, payment-method labels, contact fields and links to the existing configuration screens.
3. Toggle saved cards. Check Save enables, save, reload and confirm persistence. Restore its original value.
4. Make another edit and discard it. Check the saved value returns and Save disables.
5. Toggle manual capture. Check that explanatory text is visible, no dialog appears and nothing persists before Save changes. Discard the edit.
6. Toggle a payment method. Check that other selections remain and Discard restores the list.
7. Change the fraud protection level on the main page and discard. Verify the advanced-rules link. Test saving against a disposable account: it changes the server ruleset.
8. Reject a save request in browser developer tools. Check that the notice appears and edits remain available for retry or discard.
9. Check that a customer cannot read or write `/wc/v3/payments/settings-dataform`.
10. Check a narrow viewport and keyboard navigation. Test account-specific states separately before considering any broader adoption.

Automated tests cover REST delegation, validation, permission denial, preservation of false values, payment-method selection edits and retention of current fraud rules when changing the protection level. Additional tests exercise the adapter with the real core-data store and real Apple/Google Pay component, including entity edits and shared Discard. The browser evidence and current limitations are recorded in the PR.
