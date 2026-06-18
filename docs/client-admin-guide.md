# DMF Dental — Admin Panel User Guide

This guide is for **DMF Dental staff** (administrators and assistants) who manage enrollments, programs, and payments through the admin panel.

**Admin URL:** `https://your-domain.com/admin`  
**Public enrollment site:** `https://your-domain.com/enroll`

---

## 1. Who can access what

| Role | Access |
|------|--------|
| **Administrator** | Full access: enrollments, catalog, assistants, exports, all settings |
| **Assistant** | Only what the admin assigns (per-section permissions) |

Assistants **must** be given at least one **Enrollment** permission to see the Operations overview or enrollment list. Without enrollment permissions, they can still log in but will not see enrollment screens.

Only the **Administrator** can create or edit assistant accounts under **Administration → Assistants**.

---

## 2. Logging in

1. Open `https://your-domain.com/admin`
2. Enter your email and password
3. After login, you land on **Operations overview**

Use **Sign out** at the bottom of the sidebar when finished.

**Password tips**

- Do not share admin credentials
- Contact your system administrator to reset a password
- Assistants use their assigned `@dmfdental.com` email (created by admin)

---

## 3. Operations overview (home)

The home screen shows three workflow counts:

| Card | Meaning |
|------|---------|
| **Awaiting payment** | Student enrolled but has not paid yet (no bank transfer submitted) |
| **Pending verification** | Bank transfer proof uploaded; waiting for staff to verify |
| **Balance due** | Down payment received; tuition balance still outstanding |

Click a card to open the **Enrollments** list filtered to that tab.

---

## 4. Enrollment records

**Navigation:** Enrollment → Enrollments

### List tabs

| Tab | Shows |
|-----|--------|
| **All** | Every enrollment |
| **Needs action** | Any enrollment requiring payment, verification, or balance follow-up |
| **Awaiting payment** | Pending enrollments with no payment yet |
| **Pending verification** | Submitted bank transfer awaiting approval |
| **Balance due** | Partially paid enrollments with remaining tuition |

### Useful list actions

- **Search** — name, email, reference number
- **Filters** — status, date range, program, payment type
- **Export CSV** — only if your account has export permission (admins and permitted assistants)

### Opening a record

Click a row to view full enrollment details. Assistants only see sections they are allowed to view (profile, academic, payments, etc.).

---

## 5. Handling payments

### Card / online payment (Paymongo)

Students pay on the public site. Status updates automatically when Paymongo confirms payment.

If a payment looks stuck:

1. Open the enrollment record
2. Use **Refresh payment totals** (if available on your account)
3. Check the **Payments** tab for status

### Bank transfer

1. Student receives a signed link to upload proof of payment
2. Enrollment appears under **Pending verification**
3. Open the enrollment → **Payments** tab
4. Review proof images and reference
5. Click **Mark as paid** (verify) when confirmed

After verification, balances and enrollment status update automatically.

---

## 6. Copy payment links

For enrollments awaiting payment or balance, staff with the right permission can copy:

- Pay balance link
- Bank transfer link

Use these from the enrollment list or record view to send follow-up reminders to students.

---

## 7. Catalog management

**Navigation:** Catalog

| Screen | Purpose |
|--------|---------|
| **Categories** | Group programs/packages |
| **Programs** | Individual courses, pricing, early-bird tiers |
| **Packages** | Bundled offerings |
| **Schedules** | Batches (dates, mode, slots) tied to programs |

### Pricing notes

- **Full price** — standard tuition
- **Early bird (1st / 2nd tier)** — optional discounted prices with deadlines
- When a student enrolls, their price is **snapshotted** on the enrollment record (historical accuracy)

### Schedules

- Schedules with existing enrollments **cannot be deleted**
- Edit carefully after students are assigned to a batch

---

## 8. Managing assistants (admin only)

**Navigation:** Administration → Assistants

1. **Create** — name, email username, password, role permissions
2. Assign permissions by group:
   - Enrollment sections (profile, academic, payments, etc.)
   - Enrollment tools (export, verify bank transfer, copy links)
   - Catalog access (view/create/edit/delete per resource)

**Best practice:** Give assistants the **minimum** permissions they need. For example:

- Front desk: list + applicant profile + payments tab
- Finance: payments + verify bank transfer + export
- Catalog editor: catalog permissions only (no enrollment list unless needed)

---

## 9. Daily workflow checklist

### Morning

- [ ] Open **Operations overview** — review counts
- [ ] Work **Pending verification** tab — verify bank transfers
- [ ] Work **Awaiting payment** tab — follow up unpaid enrollments

### Ongoing

- [ ] Answer student questions using reference numbers from enrollment records
- [ ] Export CSV for reporting (if permitted)

### After catalog changes

- [ ] Confirm programs/packages/schedules are **Active**
- [ ] Verify pricing and early-bird deadlines on the public `/enroll` page

---

## 10. Troubleshooting (staff)

| Issue | What to try |
|-------|-------------|
| Cannot log in | Confirm email/password; contact admin for reset |
| Assistant sees empty enrollment menu | Admin must assign at least one `enrollment.*` permission |
| Export button missing | Admin must grant **Export enrollments (CSV)** permission |
| Student fully paid early bird before deadline but Remaining is not zero | Use **Refresh payment totals** once. Fully settled early bird enrollments should stay at ₱0 after the deadline |
| Partial payment before deadline — balance jumped to list price after deadline | Expected: only students who **fully settled** early bird before the deadline keep that rate; partial payers owe list price minus paid |
| Tuition paid / remaining look wrong (not deadline-related) | Use **Refresh payment totals** once; contact IT if still incorrect |
| Student paid but status still pending | Refresh payment totals; check Paymongo dashboard; verify webhook is configured (IT) |
| Bank proof won't open | Confirm you have Payments tab or verify permission |

For technical issues (site down, payments not syncing, login errors), contact your **developer / IT administrator** — do not change server settings.

---

## 11. Data you should not share

- Admin passwords
- Paymongo secret keys
- Signed payment URLs (they allow payment actions)
- Student bank transfer proof files outside the admin panel

---

## 12. Support contacts

| Area | Contact |
|------|---------|
| Admin access / assistant permissions | DMF Dental Administrator |
| Payment gateway (Paymongo) | Paymongo merchant support |
| Website / hosting issues | Your development team |

---

*Document version: 1.0 — aligned with production readiness release (June 2026)*
