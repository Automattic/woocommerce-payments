# Express checkout guidance

- For refresh changes, trace the product, classic cart, and classic checkout handlers separately. Do not assume one handler or WooCommerce's own request cancellation covers every path.
- Check pending, failed, superseded, and successful refreshes. A stale completion must not publish cart data, mount stale Elements, or release a newer refresh's guard. Verify that a later healthy refresh restores the intended interaction after failure.
- A visual overlay does not establish that keyboard or assistive-technology activation is blocked. Keep the interaction guard and visible busy state consistent, and provide an accessible indication when an action is temporarily unavailable.
- Test overlapping refreshes with controlled out-of-order completion. When changing wallet confirmation behavior, distinguish checks of the surrounding UI from a completed wallet payment with the expected amount and shipping selection.
