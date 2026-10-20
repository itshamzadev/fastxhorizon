# Fast X Horizon Customer Wallet Withdrawals

## Architecture reused

Customer wallet balances continue to use the existing 6Valley `users.wallet_balance`
cache and `wallet_transactions` ledger. Balance-changing operations are recorded by
`CustomerWalletLedgerService`, which locks the customer row and supports idempotency.
Withdrawal requests use the existing `CustomerWalletWithdrawalRequest` model and the
admin-managed `WithdrawalMethod` table rather than a second payout system.

## Withdrawal and hold flow

`wallet_withdrawal_enabled` remains disabled by default and must be enabled through
the existing settings architecture. A customer request records the amount and a
`held_amount` snapshot. Pending, under-review, and approved requests reduce the
customer's withdrawable balance:

`withdrawable = wallet_balance - active withdrawal holds`

Normal wallet debits are checked against active holds by the ledger service, so a
pending withdrawal cannot be spent again. A request is not debited at submission;
the wallet is debited exactly once when an authorized admin marks an approved request
as paid. Rejected or customer-cancelled pending requests release the hold by leaving
the ledger balance unchanged and moving the request out of the active statuses.

## Admin settings path

The enable switch and minimum, maximum, and daily limits are available in the
existing Admin customer settings page under the Customer Wallet section. The
default remains disabled (`0`); an authorized admin must enable the customer
wallet and wallet withdrawals before the customer action appears. Withdrawal
methods are managed separately in the existing Admin third-party Withdrawal
Methods page.

## Methods and limits

The customer form reads active methods from `withdrawal_methods` with
`customer_status = 1`. The additive migration seeds Bank Transfer, JazzCash, and
Easypaisa only when those methods do not already exist. Their fields remain editable
through the existing admin withdrawal-method management. Methods can be disabled
without code changes.

The settings `wallet_withdrawal_min_amount`, `wallet_withdrawal_max_amount`, and
`wallet_withdrawal_daily_limit` configure limits. A zero value means no limit for
that setting. The wallet remains canonical PKR; no customer-supplied exchange rate
is accepted.

## Statuses and administration

Supported lifecycle statuses are `pending`, `under_review`, `approved`, `paid`,
`rejected`, and `cancelled`. Admin review is available at the existing Customer
Wallet Withdrawals page. Marking a request paid requires a payment reference and
requires the request to be approved first. The payment ledger entry uses the
withdrawal reference as its idempotency key, preventing duplicate debits on retries.

Payment details are masked in the admin list where appropriate. Customer access is
restricted to requests owned by the authenticated customer. CSRF, authentication,
server-side validation, row locking, balance checks, and existing admin middleware
remain in force.

## Current limitation

The existing wallet ledger predates source-level withdrawability metadata. Until
promotional-credit source fields are introduced by a separate accounting decision,
all wallet balance not held by an active withdrawal is treated as withdrawable. No
cash withdrawal, peer-to-peer transfer, or seller payout behavior was changed.
