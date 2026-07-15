# DMF Dental Admin Panel — How to Guide

This guide covers how to use the admin panel to manage students, payments, courses, and prices.

**Where to log in (Admin Panel):** `https://dmfdentaltrainingcenter.com/admin`
**The main website students visit (Public Site):** `https://dmfdentaltrainingcenter.com`

---

## 1. Two types of staff accounts

| Account type      | What they can do                                               |
| ----------------- | -------------------------------------------------------------- |
| **Administrator** | Sees and can use everything in the admin panel                 |
| **Assistant**     | Only sees the parts the Administrator has switched on for them |

Only an **Administrator** can create staff accounts and decide what each Assistant is allowed to see or do. This is done under **Administration → Assistants** (explained in section 10).

If an Assistant logs in and can't see the students/enrollments list at all, it's because the Administrator hasn't turned that permission on for them yet.

---

## 2. Logging in and changing your password

1. Go to `https://dmfdentaltrainingcenter.com/admin`
2. Type in your email and password, then log in
3. You'll land on the **Overview** page (see section 3) — this is the home screen

**Email:** admin@dmfdental.com
**Temporary Password:** admin12345

When you're done, click **Sign out** at the bottom of the left-hand menu.

**To change your password:** Click **Edit profile**, found just above **Sign out** in the left-hand menu. From there you can update your name or set a new password whenever you like.

**Keeping accounts safe**

- Never share your password with anyone, including coworkers
- If you forget your password, ask your Administrator to reset it
- Assistant accounts use an email ending in `@dmfdental.com`, set up by the Administrator

---

## 3. The Overview page (home screen)

This is the first thing you see after logging in. It shows three boxes, each a running count of students who need something from you:

| Box                      | What it means                                                                |
| ------------------------ | ---------------------------------------------------------------------------- |
| **Awaiting payment**     | The student enrolled but hasn't paid anything yet                            |
| **Pending verification** | The student sent a bank transfer, and you still need to check and confirm it |
| **Balance due**          | The student made a down payment; they still owe the rest of the tuition      |

Click any box to jump straight to that list of students.

---

## 4. Viewing and managing student enrollments

**Where to find it:** click **Enrollments** in the left-hand menu

This is the master list of every student who has enrolled through the website.

### The tabs at the top of the list

| Tab                      | What you'll see there                                                                  |
| ------------------------ | -------------------------------------------------------------------------------------- |
| **All**                  | Every student, no matter their status                                                  |
| **Needs action**         | Everyone who needs some kind of follow-up from you (payment, verification, or balance) |
| **Awaiting payment**     | Students who enrolled but haven't paid                                                 |
| **Pending verification** | Students whose bank transfer needs to be checked and confirmed                         |
| **Balance due**          | Students who paid a down payment and still owe money                                   |

### Finding a specific student

- Use the **search box** to look up a student by name, email, or their reference number (a unique code assigned to each enrollment)
- Use **filters** to narrow the list by enrollment date, status, course/package, or how they're paying (in full or by down payment)
- If your account has permission, you'll see a **Download list (CSV)** button, which saves the current list as a spreadsheet file you can open in Excel

### Opening a student's record

Click anywhere on a student's row to open their full enrollment details. Depending on what your Administrator has allowed, you might see all of these sections, or only some:

- Their personal information (name, contact details, etc.)
- Their educational background
- Their home address
- Which course/package they picked, and how they chose to pay
- How much they've paid and how much they still owe
- Internal staff notes about this student (only visible to staff, never to the student)

### Buttons you might see on a student's record

| Button                     | What it does                                                                                                                                                   |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Copy payment link**      | Copies a personal payment link for this student to your clipboard, so you can send it to them (by text message, Messenger, or email) if they still need to pay |
| **Refresh payment totals** | Recalculates how much this student has paid and how much they still owe. Use this if the numbers look wrong or out of date                                     |
| **Edit notes**             | Opens a small box where you can write or update internal notes about this student                                                                              |
| **Delete enrollment**      | Permanently removes this enrollment record. This only appears for students who never made any payment — you can't delete a record once money has come in       |

---

## 5. Handling student payments

Students can pay in two ways: **online by card** or by **bank transfer**. Here's what to do for each.

### Online card payments

These are handled automatically — the system updates the student's status by itself once the payment goes through. You don't need to do anything.

If a student says they paid by card but their status still looks unpaid:

1. Open their enrollment record
2. Click **Refresh payment totals**
3. Check the **Payments** section on their record to see the current status

### Bank transfers (needs your review)

1. The student receives a link where they upload a photo of their deposit slip or transfer receipt as proof of payment
2. Their enrollment then shows up in the **Pending verification** tab
3. Open their record and go to the **Payments** section
4. Click **View proof of payment** to see the photo they uploaded (or **Download proof of payment** to save a copy), and check the reference number against your bank records
5. Once you've confirmed the money has actually arrived, click **Verify bank transfer** — this marks the payment as confirmed

Once you verify it, the student's balance and status update automatically. The **Verify bank transfer** button disappears after you've done this, so you won't verify the same payment twice.

---

## 6. Sending students a payment link

If a student hasn't paid yet, or still owes a balance, you can send them a direct link to pay:

1. Open the student's enrollment record (or find them in the list)
2. Click **Copy payment link**
3. Paste the link into a text message, email, or Messenger chat to the student

The link takes the student straight to their own payment page — they don't need to search the website or re-enter their details.

---

## 7. Managing courses, packages, and pricing

**Where to find it:** the **Catalog** section in the left-hand menu

This is where you control what students see when they visit the enrollment page — the courses offered, their prices, and when classes are held.

| Screen         | What it's for                                                                                                          |
| -------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **Categories** | Broad groupings used to organize courses and packages (for example, grouping related courses together)                 |
| **Programs**   | Individual courses — their name, price, and discount pricing                                                           |
| **Packages**   | A bundle of two or more programs sold together at one price                                                            |
| **Schedules**  | The specific class batches for a program — the dates, whether it's in-person or online, and how many students can join |

### Understanding pricing

- **Full price** is the normal price a student pays
- You can also set an **early bird price** (a lower price for students who enroll before a certain date) — you can even set up a second, later discount tier after the first one ends
- Once a student enrolls, their price is locked in on their record. If you change prices later, it will **not** affect students who already enrolled — only new enrollments see the new price

### About schedules (class batches)

- If students have already enrolled in a particular batch, you **cannot delete** that batch — you can still edit its details, but do so carefully since students are counting on that information

---

## 8. Payment channels — bank transfer details (Administrators only)

**Where to find it:** **Administration → Payment channels**

This screen controls the bank accounts and remittance centers that students see when they choose to pay by bank transfer during checkout. The available channels (BDO, BPI, China Bank, and Palawan Express) are already set up for you — you can update their details, but you cannot add a new one or remove one from this screen.

To update a channel:

1. Click on the channel you want to edit
2. Use the **Active** switch to turn a channel on (students can pay through it) or off (students won't see it as an option)
3. For a **bank account** channel, you can update the account name, account number, and the QR code image students scan to pay (just upload a new image — JPG or PNG file, up to 5MB — and it replaces the old one)
4. For a **remittance** channel (like Palawan Express), you can update the receiver's name and contact number
5. Click **Save**

Before saving changes, click **Preview QR** on the list to double-check exactly what students will see.

---

## 9. Enrollment agreement document (Administrators only)

**Where to find it:** **Administration → Enrollment agreement**

This is the signed agreement/contract file that students are given to download after they enroll.

1. Upload the file (PDF or Word document, up to 10MB) — this replaces whatever file was there before
2. Set the **file name** students will see when they download it
3. Set the **email address** shown to students, telling them where to send their signed copy back. Note: the system does **not** email this automatically — students are expected to send it themselves
4. Click **Save**

---

## 10. Setting up Assistant staff accounts (Administrators only)

**Where to find it:** **Administration → Assistants**

This is where you create logins for your team members and control exactly what each one can see and do.

**Creating an account:**

1. Enter the assistant's name
2. Enter the first part of their email — the `@dmfdental.com` part is added automatically for you
3. Set a password for them (they can change it later from their own profile)

**Choosing what they can access:** you'll see a list of checkboxes grouped into a few categories:

- **What they can see on a student's record** — personal info, educational background, home address, course/payment choice, balance details, and staff notes
- **What tools they can use on the student list** — things like downloading the list as a spreadsheet, copying payment links, verifying bank transfers, refreshing payment totals, editing notes, or deleting abandoned enrollments
- **What they can do in Catalog** — separately for Categories, Programs, Packages, and Schedules, you can allow viewing, creating, editing, or deleting

Only check the boxes they actually need for their job. A few examples:

- **Front-desk staff:** just enough to view the student list, see their basic info, and check payments
- **Finance/cashier staff:** ability to view payments, verify bank transfers, and download reports
- **Course coordinator:** access to Catalog only, without needing to see the student list

**Editing an existing account:** Leave the password box empty if you don't want to change it — their current password will stay the same.

**Removing someone's access:** Deleting their account immediately blocks them from logging in.

---

## 11. Suggested daily routine

**When you start your day:**

- [ ] Check the **Overview** page for the day's counts
- [ ] Go through **Pending verification** — confirm any bank transfers received
- [ ] Go through **Awaiting payment** — follow up with students who haven't paid

**Throughout the day:**

- [ ] Use a student's reference number to quickly find their record when they contact you
- [ ] Download the list as a spreadsheet whenever you need it for reporting

**Whenever you update courses or prices:**

- [ ] Double-check that the course, package, or schedule is switched to **Active** so students can see it
- [ ] Visit the enrollment page yourself to confirm prices and dates look correct

**Every so often (Administrators):**

- [ ] Double check the bank account numbers and QR codes under Payment channels are still correct
- [ ] Make sure the enrollment agreement file is the current version

---

## 12. Common problems and what to do

| Problem                                                                        | What to try                                                                                                                                                                                  |
| ------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Can't log in                                                                   | Double check your email and password; ask your Administrator to reset it if needed                                                                                                           |
| An Assistant can't see the student list at all                                 | Ask the Administrator to turn on at least one enrollment-related permission for them                                                                                                         |
| No "Download list" button showing                                              | This needs to be turned on by the Administrator for that account                                                                                                                             |
| A student fully paid before the early-bird deadline, but still shows a balance | Open their record and click **Refresh payment totals** once                                                                                                                                  |
| A student paid only part before the deadline, and now owes more than expected  | This is expected — only students who paid the **full** early-bird amount before the deadline keep that lower price; partial payments are charged the regular price for the remaining balance |
| Payment numbers look wrong for another reason                                  | Click **Refresh payment totals**; if it still looks wrong, contact your IT/developer                                                                                                         |
| Student says they paid by card, but their status hasn't changed                | Click **Refresh payment totals**, then check your payment processor account; if still unresolved, contact your IT/developer                                                                  |
| Can't open a student's proof of payment photo                                  | You may not have permission for the Payments section — ask your Administrator                                                                                                                |
| Can't see Payment channels or Enrollment agreement in the menu                 | These are only available to Administrator accounts                                                                                                                                           |

For anything technical — like the website being down, payments not going through properly, or errors you don't understand — contact your developer or IT support. Please don't try to change any server or technical settings yourself.

---

## 13. Information you should never share

- Your password, or anyone else's
- Payment processor account keys or credentials
- A student's personal payment link (it can be used to make a payment on their behalf)
- Students' proof-of-payment photos, outside of the admin panel

---

## 14. Who to contact

For any issue, question, or something that doesn't look right — payments, student records, courses, pricing, or anything else in the admin panel — contact your developer.
