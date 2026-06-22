# Task Workflow: Checkout & Payments Enhancement

This document details the execution plan, architectural decisions, and task checklist for enhancing checkout shipping calculations, voucher selections, and payment simulations.

---

## 1. Scope & Goals
- **Auto Multi-Courier Shipping Cost**:
  - Automatically fetch JNE, POS, and TIKI shipping rates in parallel or sequence when destination city is selected.
  - Combine, sort by price, and render all courier options in a unified service list (removing the need to toggle the courier dropdown manually).
- **Interactive Voucher Selection list**:
  - Render an elegant, collapsible coupon cards list above the checkout summary showing active vouchers.
  - Automatically calculate and pre-apply the best eligible product discount and shipping discount vouchers by default.
  - Allow manual override (clicking cards to apply/remove).
- **Full Status Mock Payment Simulation**:
  - Expand the mock payment popup on the order details page (`order-details.blade.php`) to allow simulating different statuses:
    - **Sukses / Settlement**: Updates payment and order status to `processing` (and updates inventory).
    - **Pending / Waiting**: Simulates waiting for payment (status stays `pending`).
    - **Gagal / Expired**: Cancels the transaction and restores the variant inventory levels.

---

## 2. Technical Strategy
- **Shipping Rates**: Modify `fetchShippingRates()` in `cart.blade.php` to fetch rates for `jne`, `pos`, and `tiki` sequentially or concurrently using `RajaOngkirService`, merge the results, sort by cost ascending, and store in `$shippingServices`.
- **Voucher Auto-Apply**:
  - In `revalidateAppliedVouchers()`, fetch all active and eligible vouchers.
  - Auto-select the best product discount and shipping discount vouchers if no user-selected override is active.
- **Mock Payment Simulation**: Update the Volt action `simulateSettlement` (or create `simulatePaymentStatus($status)`) in `order-details.blade.php` to handle statuses like `settlement`, `pending`, and `expire`, simulating Webhook notification transitions.

---

## 3. Sprint Tasks Checklist
- [ ] Implement multi-courier background fetching in `cart.blade.php`.
- [ ] Group and sort shipping services in a single unified list sorted by cost.
- [ ] Build the interactive collapsible voucher card list component in `cart.blade.php`.
- [ ] Implement auto-calculation and application of the best eligible vouchers by default.
- [ ] Add the multi-status (Settlement, Pending, Expire) mock payment simulation in `order-details.blade.php`.
- [ ] Run linter (`composer run lint`), compiler (`npm run build`), and test suite (`composer run test`) to verify.
