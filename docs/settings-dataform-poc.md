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
| Controls | `client/settings-dataform/fields.tsx` supplies native fields and custom React controls. |
| Layout | `SettingsDataFormService` supplies PHP View Config with the main page's group order. |
| Editing, dirty state, Save and Discard | The shared WooCommerce application and WordPress core-data. |
| Saving | A flat-record facade delegates through the existing WooPayments REST route, including its permission checks, validation and side effects. |

The facade is needed because the existing settings POST wraps its record in another REST response. After a successful save, it reads the authoritative settings record for core-data. A failed read after a successful write can still leave a partially completed operation; this is not a transactional save mechanism. The existing endpoint can also save local options before a later account update fails.

WooPayments does not call `unlock` itself. The companion Core POC passes its private field registration action to the integration. This still depends on the same unsupported API and is not a production-safe replacement for a public registration API.

## Coverage and remaining migration work

- Edit enablement, test mode (outside onboarding and development mode), saved cards, manual capture, statement descriptors, support contact details, account notification email, multi-currency and debug logging (outside development mode).
- Demonstrate a custom payment-method selector and express checkout controls. Payment method names come from the gateway and choices come from the settings REST response. Full activation, eligibility, duplicate notices, promotional offers and express-checkout legal copy are not reproduced.
- Keep the payout schedule and fraud protection level visible, with links to the existing controls. Editing those sections is not migrated in this POC.
- Keep express checkout customisation screens linked to the existing settings page. Subscription and Stripe Billing controls, bank-account editing, VAT collection, onboarding modals, tours, promotions and detailed account-status restrictions are not migrated.
- Test-mode and manual-capture controls include confirmation, but their full existing explanatory UI and tracking are not reproduced.
- Server errors appear in the shared notice. Per-field server error placement and the existing phone-input formatting are not reproduced.
- The main group order is retained, but this is a separate POC route. Existing Woo settings tabs, breadcrumbs, section anchors and URLs are not replaced or fully reproduced.
- The companion POC warns on document unload with unsaved changes. In-app route blocking is not implemented.

This is a bounded main-page integration, not feature parity with the current settings page or a complete migration of the earlier modern-settings prototype. Its line count must not be presented as the cost of migrating all WooPayments settings.

## Verification

1. With the constant disabled, check that the normal WooPayments settings still render and the POC entry is absent.
2. Enable the constant and open the POC. Check the main groups, payment-method labels, contact fields and links to the existing configuration screens.
3. Toggle saved cards. Check Save enables, save, reload and confirm persistence. Restore its original value.
4. Make another edit and discard it. Check the saved value returns and Save disables.
5. Enable manual capture and cancel its confirmation. Check that no edit is recorded.
6. Reject a save request in browser developer tools. Check that the notice appears and edits remain available for retry or discard.
7. Check that a customer cannot read or write `/wc/v3/payments/settings-dataform`.
8. Check a narrow viewport and keyboard navigation. Test account-specific states separately before considering any broader adoption.

Automated tests cover REST delegation, validation, permission denial, preservation of false values, payment-method selections and manual-capture confirmation. The browser evidence and current limitations are recorded in the PR.
