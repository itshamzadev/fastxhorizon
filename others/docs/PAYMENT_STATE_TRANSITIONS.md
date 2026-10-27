# Manual Payment State Transitions

`PENDING_SUBMISSION` → `PENDING_VERIFICATION` → `VERIFIED` or `REJECTED`.

`CANCELLED` is reserved for an explicit future cancellation workflow. Customers cannot set `VERIFIED`; only an authorized Admin decision endpoint can do so. Decisions use a conditional update on the pending state and a conditional order payment update, preventing repeated approval/rejection from changing the result.

Each submission stores the method/recipient/field definition snapshot, submitted values, amount, evidence metadata, timestamps and the deciding administrator. Historical snapshots are not rewritten when configuration changes.
