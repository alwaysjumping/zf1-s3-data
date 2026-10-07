# Intelligent Workspace
## Functional Requirements & Product Specification

**Document Status:** Product baseline  
**Scope:** Functional requirements, user experience, business behavior, and phased product roadmap  
**Out of Scope:** Architecture, programming languages, frameworks, databases, APIs, AI models, infrastructure, synchronization implementation, and other technical implementation decisions

---

## 1. Purpose

The Intelligent Workspace is an evolution of the existing company business system into a more convenient, context-aware, proactive, and trustworthy working environment.

The objective is not simply to add an "AI" feature. The objective is to reduce the amount of information users must remember, search for, repeat, interpret, coordinate, and manually check.

The system should help employees:

- Know what requires attention.
- Find information quickly across business areas.
- Continue unfinished work.
- Understand the current situation of customers, products, documents, communications, and other business objects.
- Remember commitments, follow-ups, waiting items, and deadlines.
- Recognize important changes.
- Coordinate work with other people.
- Reduce repetitive operations.
- Understand business impact before important actions.
- Reuse company knowledge and previous experience.
- Make better-informed decisions while remaining in control.

For managers, the system should emphasize exceptions, decisions, risks, blocked work, meaningful changes, and areas requiring intervention rather than forcing managers to review normal activity manually.

For the organization, the system should gradually turn business records, communications, documents, decisions, resolved cases, procedures, and lessons learned into reusable organizational knowledge.

---

## 2. Product Vision

> **The Intelligent Workspace helps each employee know what requires attention, find and understand relevant information, continue unfinished work, coordinate with others, remember commitments and waiting items, recognize important changes, and complete routine business work with less manual effort - while keeping users in control.**

The desired transition is:

> **From "users search for work" to "the system brings the right work to the user."**

The system should understand enough working context to help users:

1. Find information.
2. Remember unfinished work.
3. Recognize important changes.
4. Avoid mistakes.
5. Reduce repetitive operations.
6. Decide what deserves attention next.

---

## 3. Product Identity

The product should be positioned as an **Intelligent Workspace** or **Smart Business Workspace**, rather than merely an "AI feature."

Its value comes from the combination of:

- Better user experience.
- Better organization.
- Search and navigation.
- Context.
- Work awareness.
- Business rules.
- Automation.
- Knowledge reuse.
- Intelligent assistance.
- Human control.

---

## 4. Core Product Principles

### 4.1 Bring Important Work to the User

Users should not need to visit Email, CRM, Product, Documents, Notifications, Reports, and other modules merely to discover what requires attention.

The system should provide a central personal working layer that answers:

- What do I need to do?
- What am I waiting for?
- What changed?
- What should I know?

### 4.2 Put Information in Context

The system should connect related business information so users do not have to manually reconstruct relationships among:

- People.
- Customers.
- Products.
- Emails.
- Documents.
- Quotations.
- Orders.
- Tasks.
- Notes.
- Issues.
- Decisions.
- Approvals.
- Reports.

### 4.3 Remember What Humans Forget

The system should help users remember:

- Follow-ups.
- Waiting items.
- Commitments.
- Deadlines.
- Unfinished work.
- Drafts.
- Important changes.
- Requested actions.
- Promises from others.

### 4.4 Reduce Repetitive Work

The system should remember useful preferences, reuse context, prepare routine work, support templates, reduce repeated navigation, and provide quick actions.

### 4.5 Help Users Decide, but Keep Humans in Control

The system may summarize, compare, warn, organize evidence, show impact, and recommend actions.

For consequential business decisions, the system should generally organize evidence rather than pretend to be the decision-maker.

### 4.6 Reduce Noise

Intelligence must reduce information overload rather than create more alerts.

A useful attention hierarchy is:

- **Interrupt** - critical and urgent.
- **Surface** - important and prominent in My Work.
- **Record** - useful for history or digest without interruption.

### 4.7 Intelligence Must Not Become a Gatekeeper

Users must remain able to use the normal business system when intelligent assistance is unavailable, uncertain, or unwanted.

Examples:

- Original emails remain accessible.
- Original documents remain accessible.
- Manual search remains available.
- Manual data entry remains available where business policy permits.
- Users can ignore or correct suggestions.
- Users can choose another action.

### 4.8 Automate Effort Before Automating Decisions

Preferred pattern:

**Detect -> Explain -> Suggest/Prepare -> User Reviews -> Execute**

For explicitly authorized, low-risk repetitive work:

**Detect -> Automatically Execute -> Inform User**

---

## 5. User Burdens the Product Should Reduce

Every major feature should reduce one or more of these burdens:

### 5.1 Remember
"What was I supposed to do?"

### 5.2 Search
"Where is that information?"

### 5.3 Repeat
"Why do I keep doing this manually?"

### 5.4 Interpret
"What does all this information mean?"

### 5.5 Coordinate
"Who is doing this and what are we waiting for?"

### 5.6 Check
"Has anything important changed?"

A proposed feature should be questioned if it does not materially reduce one of these burdens.

---

## 6. Universal Work Model

Almost every business item can be understood through four questions:

1. **What do I need to do?**
2. **What am I waiting for?**
3. **What changed?**
4. **What should I know?**

The primary My Work categories should therefore be:

- Action Required.
- Waiting for Others.
- Changes.
- Reminders.
- Information.

Additional useful concepts include:

- Task.
- Follow-Up.
- Watch.
- Notification.
- Decision.
- Exception.
- Ownership.
- Deadline.
- Priority.
- Context.
- Relationship.
- History.
- Knowledge.
- Impact.

---

## 7. Personal Control Vocabulary

The system should use a small, consistent set of personal controls.

### 7.1 Favorite

Meaning:

> I use or access this frequently.

Applicable to:

- Customers.
- Products.
- Documents.
- Reports.
- Pages.
- Saved searches.
- Dashboards.

### 7.2 Watch

Meaning:

> Tell me when this changes.

Watch options should vary by object. For a product, for example:

- Price.
- Status.
- Specification.
- Important changes.
- Any change.

### 7.3 Remind

Meaning:

> Bring this back to me at a particular time.

Example choices:

- Later today.
- Tomorrow.
- Next Monday.
- Next week.
- Custom date/time.

### 7.4 Follow Up

Meaning:

> This requires future attention.

A follow-up should normally have:

- Related business object.
- Follow-up date.
- Optional reason/note.

### 7.5 Snooze

Meaning:

> Temporarily remove an existing work item and bring it back later.

Snooze differs from Reminder:

- **Reminder** creates a future reminder about something.
- **Snooze** temporarily hides an existing work item.

If snoozing would pass an important deadline, the system should warn the user.

---

## 8. My Work

### 8.1 Purpose

My Work should become the central personal working page.

Its purpose is:

> **Here is what matters to you now.**

It should not replace Email, CRM, Product, Documents, Reports, or other business modules. It should provide a personal working layer above them.

### 8.2 Suggested Summary

Example:

```text
MY WORK

Need Action        4
Waiting            5
Reminders          3
Important Changes  2
Tasks              7
```

### 8.3 Main Sections

Possible sections:

- Overdue.
- Today.
- Waiting.
- Reminders.
- Important Changes.
- Upcoming.
- Later.

Sections with no relevant content may be hidden.

### 8.4 Every Work Item Should Answer Five Questions

1. **What?**
2. **Why me?**
3. **Why now?**
4. **How important?**
5. **What can I do?**

Example:

```text
ACTION REQUIRED

Reply to ABC Corporation

Why me:
You own this customer conversation.

Why now:
The customer requested a response today.

Importance:
Important customer; waiting 3 days.

Actions:
[Reply] [Open] [Remind Me] [Later]
```

### 8.5 Action Required Types

Examples:

- Reply.
- Review.
- Approve.
- Confirm.
- Complete.
- Provide Information.
- Fix.
- Sign.
- Read.
- Follow Up.
- Decision Required.

### 8.6 Important vs Urgent

The system should distinguish importance from urgency.

An item may be:

- Important + Urgent.
- Important + Not Urgent.
- Normal + Urgent.
- Normal + Not Urgent.

The user should be able to ask "Why important?"

### 8.7 Mark Done

Completion may be:

- Automatic after a clear business action.
- Manual for activities such as phone calls or offline work.

Optional completion notes may be supported.

Completed work should remain in history rather than disappear as if it never existed.

### 8.8 Focus Mode

A **Start My Work** action should support sequential work:

```text
ITEM 1 OF 7

ABC Corporation needs a reply.

[Reply]

[Done] [Later] [Skip]
```

After completion:

```text
Completed.

6 important items remaining.

[Next]
```

### 8.9 My Day

A focused daily view may group:

- Overdue.
- Morning.
- Afternoon.
- Anytime Today.
- Waiting.
- Reminders.

### 8.10 "What Should I Do Next?"

The system may recommend the next item and explain why.

Example:

```text
1. Reply to ABC Corporation
   Due today.

2. Review Product P-102
   Blocking 3 quotations.

3. Complete Monthly Report
   Due tomorrow.
```

Recommendations must remain explainable and dismissible.

### 8.11 Empty State

Instead of "No records":

```text
You're caught up.

Nothing currently requires your attention.

Waiting for others: 3
Upcoming tomorrow: 4

[View Waiting]
```

---

## 9. Waiting and Follow-Up

### 9.1 Waiting Is a First-Class Work State

Waiting should apply beyond email.

Examples:

- Waiting for customer reply.
- Waiting for manager approval.
- Waiting for accounting.
- Waiting for document review.
- Waiting for requested information.
- Waiting for product update.
- Waiting for external response.
- Waiting for cross-site transfer.
- Waiting for processing.

### 9.2 Waiting Information

A Waiting item may show:

```text
Waiting for:
Mary

Since:
Oct 3

Expected:
Oct 6

Related:
Quotation Q-382
```

### 9.3 Waiting Lifecycle

A waiting item may evolve:

```text
Waiting
-> Due Today
-> Overdue
```

Actions may include:

- Open.
- Send Follow-Up.
- Extend.
- Stop Waiting.

### 9.4 Waiting Resolved

When the expected event occurs:

```text
WAITING RESOLVED

Mary approved Q-382.

[Continue Work]
```

### 9.5 People Waiting on Me

The system should provide the reverse view:

```text
PEOPLE WAITING ON ME

Mary - Product review
John - Pricing answer
Accounting - Monthly report
```

Conceptually:

> **I AM WAITING FOR THEM <-> THEY ARE WAITING FOR ME**

### 9.6 My Commitments

The system should support a dedicated view of commitments made by the user.

Examples:

- Send revised quotation tomorrow.
- Provide report Friday.
- Call customer next week.

### 9.7 Commitments From Others

Promises from other people may be tracked as Waiting items.

---

## 10. Tasks

### 10.1 Personal Tasks

Tasks should support:

- Title.
- Owner.
- Due date.
- Status.
- Related business context.
- Optional note.

Suggested statuses:

- Not Started.
- In Progress.
- Waiting.
- Completed.
- Cancelled.

### 10.2 Contextual Task Creation

Creating a task while viewing a customer, product, email, document, quotation, or issue should automatically carry that context into the task.

### 10.3 Delegated Work

Delegated work should be distinguishable from the user's own responsibilities.

Show:

- Assignee.
- Due date.
- Status.
- Related context.

---

## 11. Daily and Return-to-Work Experiences

### 11.1 Morning Briefing

Example:

```text
GOOD MORNING

4 things need your attention.

1. ABC customer needs reply.
2. Product P-102 review due today.
3. Monthly report reminder.
4. XYZ quotation follow-up due.

[Start My Work]
```

### 11.2 End-of-Day Check

Optional:

```text
TODAY

11 items completed
5 waiting
2 important items remain

Tomorrow:
4 planned items
```

This feature must be disable-able.

### 11.3 Return-from-Absence Mode

After several days away:

```text
WELCOME BACK

Important while you were away:

8 actions
5 waiting items resolved
4 customer changes
3 product changes
2 decisions

[Show Important First]
```

---

## 12. Universal Search and Find

### 12.1 Global Search

A global search box should be available throughout the system:

```text
Search or ask anything...
```

Initial supported input should include:

- Customer names.
- Person names.
- Product identifiers.
- Quotation/order identifiers.
- Document titles.
- Report names.
- Other common business identifiers.

### 12.2 Grouped Results

Results should be grouped by business type:

- Customers.
- People.
- Products.
- Emails.
- Documents.
- Tasks.
- Business records.
- Other relevant categories.

### 12.3 Best Match

The strongest result should be clearly presented first.

### 12.4 Quick Preview

Users should be able to preview a result without fully navigating away.

Example:

```text
ABC CORPORATION

Important Customer
2 items need attention
Last activity: Today
Open quotation: Q-382

[Open]
```

### 12.5 Everything About...

A cross-module destination should aggregate information about a business entity.

Example:

```text
EVERYTHING ABOUT ABC CORPORATION

Current Situation

People
Communication
Orders
Quotations
Products
Documents
Tasks
Decisions
Notes
Timeline
```

### 12.6 Search by Meaning

Later versions should support searches such as:

- Customer waiting for quotation.
- Emails from John last week.
- P-102 complaints.
- Documents about delivery policy.
- Customers I have not contacted recently.
- Things waiting for Mary.

Results should explain why they matched.

### 12.7 Search by Action / Command Palette

Examples:

```text
new task
new email
my waiting
my reminders
```

Search can become both navigation and command entry.

### 12.8 Saved Searches

Examples:

- Important customers needing contact.
- Products awaiting review.
- My unanswered customer emails.
- Documents changed this week.
- Open tasks.

### 12.9 Recent Searches

Users may revisit recent searches and clear them.

---

## 13. Recent, Favorites, and Continue Work

### 13.1 Recently Viewed

A common history across modules should show recently viewed objects with timestamps where useful.

Users may:

- Open.
- Pin.
- Favorite.
- Clear history.

### 13.2 Continue Where I Left Off

The system should surface:

- Draft emails.
- Last-opened customer.
- Product review in progress.
- Report/document last viewed.
- Unfinished forms.
- Previous search/work context.

### 13.3 Draft Recovery

If unfinished work exists after accidental closure:

```text
Unsaved work found.

[Recover] [Discard]
```

### 13.4 Do This Again

For suitable repetitive actions, the system may offer a "Do This Again" shortcut.

---

## 14. Smart Object View

### 14.1 Goal

An object page should answer:

> **What is happening with this object right now?**

It should not merely display database fields.

### 14.2 Consistent Object Header

Example:

```text
ABC CORPORATION                 Favorite  Watch
Important Customer - Active

[Email] [Task] [Note] [More]

ATTENTION
...
```

A consistent header should reduce training across object types.

### 14.3 Right Now

Every important object may have a **Right Now** section.

Customer example:

- Latest email needs reply.
- Quotation waiting.
- Contract due.
- Open issue.

Product example:

- Price changed.
- Specification approval pending.
- Quotations use previous price.

Document example:

- New version.
- Review requested.
- Open comments.

Person example:

- Waiting reply.
- Tasks.
- Recent communication.

### 14.4 Attention

Attention should surface meaningful work or risk, not ordinary activity.

### 14.5 Since You Last Viewed

Example:

```text
NEW SINCE YOUR LAST VISIT

Important:
- Contract v4 uploaded.
- Quotation status changed.
- Billing address changed.

Other:
- 3 emails
- 1 note

[Review Changes]
```

After review:

```text
You're up to date.
```

### 14.6 Related Information

A universal Related area may show:

- People.
- Emails.
- Documents.
- Products.
- Orders.
- Quotations.
- Tasks.
- Notes.
- Issues.
- Decisions.

### 14.7 Relationship Map

For complex situations, an optional visual relationship map may show connections among customers, people, products, contracts, projects, reports, and other objects.

### 14.8 Business-Context Breadcrumb

Navigation should support business context, not only module hierarchy.

Example:

```text
Customer -> Product -> Quotation -> Email
```

### 14.9 Smart Back

The system should be able to return users to the meaningful business context, such as:

```text
Back to ABC Corporation
```

### 14.10 Side-by-Side View

Useful combinations include:

- Email + Customer.
- Document + Comments.
- Product + Change History.

### 14.11 Quick Preview

Grids and search results should support quick contextual previews.

---

## 15. Customer Smart View

A Customer Smart View may include:

```text
ABC CORPORATION
Important Customer - Active

[Email] [Task] [Note] [Follow Up]

ATTENTION
- Latest email needs reply
- Contract review due today

RIGHT NOW
- Quotation Q-382 waiting for customer
- 3 active orders
- 1 open issue
- Contract under review

RECENT ACTIVITY
...

PEOPLE
...

RELATED
Emails / Orders / Documents / Products / Tasks / Notes
```

### 15.1 Customer Important Information

Important information may include:

- Delivery requirements.
- Changed billing contact.
- Preferred communication method.
- Special customer requirements.

These are not necessarily tasks, but they are important context.

### 15.2 Customer Timeline

Human-readable chronological activity should support filters such as:

- Important.
- All.
- Communication.
- Business.
- Documents.
- Notes.

### 15.3 Customer Watch

Users may watch:

- Important changes.
- New important email.
- Contract changes.
- New issue.
- Notes.
- Any change.

---

## 16. Product Smart View

A Product Smart View may include:

```text
PRODUCT P-102
Active

Current Price: $135

ATTENTION
Specification v6 requires review.

IMPORTANT CHANGE
Price: $120 -> $135

IMPACT
3 open quotations use previous price.
14 active customers use this product.

KNOWN ISSUES
...

RELATED
Customers / Orders / Quotations / Documents / Emails
```

### 16.1 Product Change History

Show business-readable changes:

- Price.
- Specification.
- Description.
- Status.

Technical audit detail should remain separate.

### 16.2 Product Knowledge

Examples:

- Current specification.
- Current manual.
- Approved procedures.
- Known issues.
- Previous solutions.
- Important decisions.

---

## 17. Activity and Change Awareness

### 17.1 Human-Readable Activity Timeline

Examples:

```text
Today
09:42 Customer email received.

Yesterday
15:32 Contract v4 uploaded.
14:11 Mary added note.
```

### 17.2 Important Events vs All Events

Default to meaningful activity. Allow users to view all activity when needed.

### 17.3 What Changed?

The system should translate changes into business-readable differences.

Example:

```text
Price
$120 -> $135

Specification
v5 -> v6

Status
Review -> Approved
```

### 17.4 Before/After Comparison

For significant changes, users should be able to compare old and new values or versions.

### 17.5 Universal Attention Column

Major grids may include an Attention column:

- Action Required.
- Important Change.
- Reminder.
- Waiting.
- Nothing.

Users should be able to sort by Attention.

### 17.6 Only Show What Needs Attention

Major lists may support a filter such as:

```text
12 of 4,821 customers need attention.
```

---

## 18. Smart Inbox and Communication Intelligence

### 18.1 Smart Inbox Goal

Email should move beyond read/unread.

Useful conversation states include:

- Unread.
- Read.
- Needs Reply.
- Needs Action.
- Waiting for Reply.
- Follow Up.
- Snoozed.
- Completed.
- Information Only.

### 18.2 Inbox Attention Summary

Example:

```text
Needs Reply     6
Waiting         8
Follow-Up       3
Important       4
Unread         21
```

### 18.3 Smart Inbox Rows

Rows may show:

- Attention.
- Sender.
- Subject.
- Status.
- Age.
- Requested deadline.

### 18.4 Smart Email Header

When reading an email, show relevant context such as:

- Status.
- Age.
- Requested deadline.
- Related customer.
- Related product.
- Related order/quotation.
- Quick actions.

### 18.5 Email Summary

Example:

```text
SUMMARY

Customer wants 500 units of P-102.
Requested delivery before Oct 20.
Three questions require answers.
```

Original email remains authoritative.

### 18.6 Question and Request Extraction

Example:

```text
QUESTIONS / REQUESTS

[ ] Is P-102 available?
[ ] What is the latest price?
[ ] Can 500 units arrive before Oct 20?
[ ] Send current specification.
```

### 18.7 Requested Action Detection

The system may suggest:

- Create Task.
- Add to My Work.
- Ignore.

User confirmation is required where appropriate.

### 18.8 Deadline Detection

Example:

```text
Possible deadline:
Friday, Oct 9

Source:
Customer email

[Confirm] [Change] [Ignore]
```

### 18.9 Commitment Detection

If the user writes:

> I'll send the revised quotation tomorrow.

The system may offer:

```text
You made a commitment:
Send revised quotation tomorrow.

[Track] [Ignore]
```

If another person promises something, the system may offer to track it as Waiting.

### 18.10 Communication Model

Conceptually:

- **They ask me -> My Work**
- **I ask them -> Waiting for Others**
- **I promised -> My Commitments**
- **They promised -> Waiting for Others**

### 18.11 Decision Detection

If communication contains a meaningful decision, the system may suggest saving it as a formal Decision.

### 18.12 Important Fact Detection

Example:

```text
Possible new customer address

Email:
150 Main Street

Current CRM:
120 Main Street

[Review Change]
```

Never silently update important business data.

### 18.13 New Contact Detection

If an email introduces a new business contact, suggest adding or linking the contact.

### 18.14 Business Reference Detection

Recognize references such as:

- Product IDs.
- Quotation IDs.
- Order IDs.
- Project/report names.

Offer to link them when appropriate.

### 18.15 Conversation Summary

A conversation summary may include:

- Purpose.
- Participants.
- History.
- Current status.
- Open items.

### 18.16 Conversation State

Suggested states:

- Active.
- Waiting for Me.
- Waiting for Them.
- Resolved.
- Information Only.

### 18.17 Complete / Reopen Conversation

Users may mark a conversation complete.

If a new reply arrives, the system may reopen it automatically.

### 18.18 Smart Follow-Up

When no response arrives within an expected period, the system may suggest a follow-up.

### 18.19 Follow-Up History

Show previous follow-ups to avoid unnecessary repeated contact.

### 18.20 Recipient Intelligence

Possible warnings:

- External recipient.
- Recipient inconsistent with current customer/context.
- Unusual recipient for this conversation.

### 18.21 Attachment Intelligence

Warnings may include:

- Mentioned attachment missing.
- Outdated file.
- Draft version attached instead of final version.

Attachment context may show:

- Type.
- Related object.
- Previous version.
- Preview.
- Compare.

### 18.22 Smart Draft Assistance

Writing assistance may include:

- Professional.
- Shorter.
- Friendlier.
- Clearer.
- Grammar correction.
- Translation.

Business intents may include:

- Follow up.
- Confirm order.
- Request information.
- Decline politely.
- Acknowledge request.
- Request approval.

### 18.23 Reply Based on Context

The system may help prepare a reply using authorized and sufficiently fresh business information.

The source and freshness of important facts should remain visible.

### 18.24 Reply Checklist / Send Check

Example:

```text
SEND CHECK

Recipient verified.
Subject present.
Availability answered.
Price answered.

Warning:
Delivery question may be unanswered.

Warning:
Customer requested specification, but no attachment is included.

[Review] [Send Anyway]
```

This should appear only when useful, not for every simple message.

### 18.25 Complaint and Positive Feedback Recognition

Possible actions:

- Create/Link Issue.
- Save Feedback.

### 18.26 Communication Search by Intent

Examples:

- Waiting for me.
- Waiting for them.
- Promises.
- Complaints.
- Unresolved conversations.

### 18.27 Shared Communication Awareness

Warn when a colleague has already replied or is currently handling the request, where appropriate.

### 18.28 Handoff

A conversation can be handed off with its context.

### 18.29 Out-of-Office Handoff

Users may transfer responsibility for relevant work during absence.

### 18.30 Communication Digest

After absence or during high volume, summarize important communication rather than showing every event individually.

---

## 19. Smart Forms and Data Quality

### 19.1 Smart Defaults

Suggest likely values from context/history, but clearly mark them as suggestions.

### 19.2 Duplicate Detection

Example:

```text
POSSIBLE DUPLICATE

New:
ABC Corporation Ltd.

Existing:
ABC Corporation

Same:
Phone
Address

[Compare] [Use Existing] [Create Anyway]
```

### 19.3 Input Error Detection

Warn about likely mistakes without silently changing data.

### 19.4 Unusual Values

Example:

```text
UNUSUAL PRICE

Entered:
$125

Recent range:
$1,100-$1,300

This may be a typo.

[Review] [Keep $125]
```

### 19.5 Data Confidence

Where useful, show whether information is:

- Verified.
- Possibly outdated.
- Needs confirmation.

### 19.6 Conflicting Information

Example:

```text
CUSTOMER ADDRESS CONFLICT

CRM:
120 Main Street

Latest contract:
150 Main Street

[Review]
```

### 19.7 Missing Information

Profile completeness should be contextual. Do not constantly nag users about irrelevant missing fields.

### 19.8 Intelligent Confirmation

Instead of generic "Are you sure?", explain consequences.

---

## 20. Knowledge and Document Intelligence

### 20.1 Core Goal

> **What does our company already know, and how can the user find and reuse it quickly?**

### 20.2 Company Knowledge in Context

Object pages may surface:

- Relevant documents.
- Known issues.
- Previous customer questions.
- Decisions.
- Notes.
- Reports.
- Resolved problems.
- Procedures.
- Best practices.

### 20.3 Has This Happened Before?

Show similar historical cases and how they were resolved.

Do not imply the same solution is automatically correct.

### 20.4 Previous Solutions

Resolved cases should be reusable as knowledge.

### 20.5 Known Issues

Product pages may show known and resolved issues.

### 20.6 Lessons Learned

A lesson may record:

- Situation.
- Lesson.
- Related objects.
- Evidence.

### 20.7 Best Practices

Best practices should surface contextually rather than requiring users to search a separate manual.

### 20.8 Procedures

Relevant procedures should appear in the context of the work being performed.

### 20.9 Official vs Internal Knowledge

The system should distinguish:

**Official knowledge**
- Approved policies.
- Procedures.
- Manuals.
- Product information.
- Templates.

**Internal knowledge**
- Notes.
- Cases.
- Emails.
- Decisions.
- Informal discussions.

### 20.10 Knowledge Authority and Confidence

Useful dimensions include:

- Source.
- Status.
- Version.
- Effective date.
- Current/outdated status.
- Confirmation.

### 20.11 Current Version

The system should help users identify the current approved version.

### 20.12 Outdated Document Warning

Example:

```text
NEWER VERSION AVAILABLE

You are viewing:
Specification v5

Current received version:
v6

[Open v6] [Compare]
```

### 20.13 Document Summary

A document summary may include:

- Purpose.
- Important sections.
- Key changes.
- Related objects.

### 20.14 Explain This Document

Explanations may be tailored to the user's role where appropriate.

### 20.15 Ask the Document

Users may ask:

- What is the warranty?
- What temperature is specified?
- What changed?
- Where is a particular requirement described?

### 20.16 Source-Based Answers

Important answers must point to the source document/version/section where possible.

### 20.17 Compare Versions

Show meaningful differences, not merely textual noise.

### 20.18 What Changed That Matters to Me?

Change relevance may differ by role.

Examples:

- Sales.
- Engineering.
- Support.
- Management.

### 20.19 Document Action Items

Extract possible:

- Actions.
- Owners.
- Deadlines.

User confirms before creating business tasks.

### 20.20 Document Decisions

The system may suggest saving meaningful decisions found in documents.

### 20.21 Meeting Knowledge

Possible meeting summary:

- Attendees.
- Decisions.
- Action items.
- Open questions.
- Related business objects.

### 20.22 Decision History

A Decision should capture:

- What was decided.
- Reason.
- Who decided.
- Date.
- Related objects.
- Evidence.

### 20.23 Why Did We Decide This?

Users should be able to retrieve the rationale and evidence behind important business decisions.

### 20.24 Decision Reversal

Do not erase the old decision. Preserve:

- Previous decision.
- New decision.
- Reason for change.
- Date.
- Responsible people.

### 20.25 Company Q&A

Users may ask questions based on authorized company knowledge.

Answers must not invent information.

### 20.26 Search by Business Meaning

Examples:

- Previous similar cases.
- Complaints.
- Decisions.
- Solutions.

### 20.27 Expert Finder

Help users find colleagues with relevant experience.

The purpose is assistance, not employee ranking or surveillance.

### 20.28 Knowledge Gaps

Repeated questions without an approved answer may trigger:

```text
No approved knowledge article exists.

[Create Knowledge Article]
```

### 20.29 Knowledge Article Lifecycle

Suggested states:

- Draft.
- Under Review.
- Approved.
- Needs Review.
- Outdated.
- Archived.

### 20.30 Is This Still True?

Warn when older knowledge may have become invalid because related business information changed.

### 20.31 Contradictory Knowledge

Surface conflicting sources and identify the authoritative source where known.

### 20.32 Knowledge Trust Hierarchy

Conceptually, the system should understand different authority levels, such as:

1. Current approved policy/procedure.
2. Current approved product/document data.
3. Recorded business decision.
4. Verified historical case.
5. Internal knowledge article.
6. Notes/email/informal discussion.

The user does not necessarily need to see these numbers.

### 20.33 Organizational Memory

The long-term goal is:

```text
Employee Knowledge
+ Email History
+ Documents
+ Business Records
+ Decisions
+ Resolved Cases
+ Procedures
= Organizational Memory
```

This should reduce knowledge loss when experienced employees leave.

---

## 21. Impact Intelligence

### 21.1 Change Impact

After a change:

```text
PRICE CHANGED
$120 -> $135

POSSIBLE IMPACT

3 open quotations use old price.
2 proposals reference previous price.

[Review Impact]
```

### 21.2 Before-Action Impact

Before an important change, show affected business objects.

Example:

```text
DEACTIVATE PRODUCT P-102?

Affected:
3 open quotations
4 active orders
14 customers
1 unresolved issue

[Review Impact] [Continue]
```

### 21.3 What Will Happen?

Before complex actions, explain consequences in business language.

### 21.4 What Happened?

After an unexpected state change, explain:

- Who changed it.
- When.
- Why, if known.
- Related evidence.

### 21.5 Secondary Consequences

Example:

Customer address changes while pending orders still use the previous address.

The system should surface the possible secondary effect.

---

## 22. Automation and Proactive Assistance

### 22.1 Three Automation Levels

#### Level 1 - Suggest

The system notices a situation and suggests an action.

#### Level 2 - Prepare

The system prepares work, but the user reviews and executes.

#### Level 3 - Automatic

Only suitable low-risk, explicitly authorized routines should execute automatically.

### 22.2 Smart Follow-Up

Example:

```text
FOLLOW-UP SUGGESTED

ABC quotation
Waiting 7 days

No reply recorded.

[Prepare Follow-Up]
[Remind Tomorrow]
[Already Handled]
```

### 22.3 Follow-Up Escalation

Example progression:

- Day 3 - follow-up suggested.
- Day 5 - second reminder.
- Day 7 - response overdue.
- Day 10 - consider escalation.

Available actions may include:

- Follow Up Again.
- Call Customer.
- Extend.
- Close.

### 22.4 Smart Escalation

For internal work:

- Remind assignee.
- Reassign.
- Notify manager.
- Extend deadline.

Escalation should be configurable and transparent.

### 22.5 Commitment Monitoring

Track promises made by the user and surface them when due.

### 22.6 Deadline Risk

Warn before work becomes overdue when risk is reasonably clear.

Example:

```text
AT RISK

Monthly report

Due:
Tomorrow

Status:
Not started

Typical preparation:
4 hours

[Start]
```

### 22.7 Approaching Deadlines

Useful grouping:

- Today.
- Tomorrow.
- Next 3 Days.
- At Risk.

### 22.8 Dependency Awareness

Show when work is waiting on another task or decision.

### 22.9 Blocked Work

Dedicated Blocked view may include:

- Quotation waiting for product approval.
- Report waiting for accounting data.
- Contract waiting for legal review.

### 22.10 What Is Blocking Me?

The user may ask for a summary of current blockers and who/what is awaited.

### 22.11 Automatic Unblock Notification

When a dependency completes:

```text
READY TO CONTINUE

Product review completed.

[Continue Work]
```

### 22.12 Routine Detection

If the user repeatedly performs the same workflow, the system may suggest creating a routine.

### 22.13 Personal Routines

Support:

- Daily.
- Weekly.
- Monthly.
- Other appropriate schedules.

### 22.14 Routine Checklist

Some recurring processes should be checklists rather than fully automated processes.

### 22.15 Recurring Tasks

Recurring tasks should appear naturally in My Work.

### 22.16 Automatic Preparation

Example:

```text
MONDAY SALES REPORT

Prepared for you.

Includes:
- Last week's sales
- Customer changes
- Open quotations
- Important exceptions

[Review]
```

### 22.17 Pre-Filled Work

New business records may be pre-filled from context, clearly marked as suggested and reviewable.

### 22.18 Exception-Based Work

Instead of reviewing thousands of normal records, surface the small subset needing attention.

Possible exception types:

- Unusual prices.
- Missing information.
- Delayed approvals.
- Address conflicts.
- Unusual quantities.
- Duplicates.

### 22.19 Exception Inbox

Possible categories:

- Critical.
- Important.
- Review.

### 22.20 Normal Range Awareness

Warn when a value is far outside normal/history, while allowing the user to keep it.

### 22.21 Unusual Quantity / Behavior

Examples:

- Quantity outside typical customer range.
- No order despite normal monthly pattern.
- Issue spike.
- Approval taking much longer than normal.
- Late payment.

### 22.22 Trend Detection

Examples:

- Complaint increase.
- Order-frequency decrease.
- Response time worsening.

### 22.23 Workflow Completion Awareness

After a business step completes, suggest logical next steps.

Example after quotation approval:

- Send to Customer.
- Create Order.
- Download.
- Mark Complete.

### 22.24 Missing-Step Detection

Warn when an expected business step appears to have been skipped.

### 22.25 Stalled Work

Work may be considered stalled even before it is formally overdue if activity is unusually absent.

### 22.26 Forgotten Drafts

Surface old unfinished drafts.

### 22.27 Forgotten Requests

If a customer request was read but no reply/task is found after an appropriate period, suggest review.

### 22.28 Already Handled

Users must be able to say:

```text
[Already Handled]
```

Optionally specify:

- Email.
- Phone.
- Meeting.
- Another message.
- Other.

### 22.29 Dismiss With Reason

Possible reasons:

- Not relevant.
- Already handled.
- Incorrect suggestion.
- Do not suggest again.

### 22.30 User Automation Rules

Plain-language model:

```text
WHEN ...
AND ...
THEN ...
```

Examples:

```text
WHEN an important customer email arrives
AND it asks me a question
THEN put it in My Work
```

```text
WHEN a quotation has no reply for 5 days
THEN suggest follow-up
```

### 22.31 Rule Templates

Examples:

- Follow-up.
- Watch.
- Deadline.
- Important Customer.
- Approval.

### 22.32 Personal vs Team Rules

Distinguish personal productivity automation from team/company workflow.

### 22.33 Automation History

Users should be able to see what automation did and why.

### 22.34 Undo Automatic Actions

Where possible, support undo for low-risk automatic actions.

### 22.35 Automation Control Center

Suggested areas:

- Active.
- Paused.
- Suggested.

---

## 23. Personalization and User Convenience

### 23.1 Personal Workspace

Possible sections:

- My Work.
- Favorites.
- Recent.
- Waiting.
- Reminders.
- Watching.
- Saved Searches.
- Drafts.
- My Tasks.
- My Notes.

### 23.2 Customizable Home

Users may choose which useful cards appear on their personal home page.

### 23.3 Personal Shortcuts

Users may pin frequently used pages, searches, actions, customers, products, reports, or other business objects.

### 23.4 Keyboard-Friendly Operation

Support efficient keyboard navigation and a command palette for power users.

### 23.5 Remembered Layouts

The system may remember:

- Column preferences.
- Grid presets.
- Sort.
- Filters.
- Compact/comfortable view.

### 23.6 Recently Used Values

Where useful, forms may offer recently used values without silently selecting them.

### 23.7 Personal Templates

Users may save reusable personal templates for suitable work.

### 23.8 Multiple Workspaces

Where useful, users may maintain different personal workspaces for different responsibilities.

### 23.9 Personalization Suggestions

After repeated behavior:

```text
Add "Customer Follow-Ups" to your Home page?

Reason:
You opened this view frequently during the last two weeks.
```

### 23.10 User Control

Users should be able to:

- Accept.
- Not Now.
- Less Like This.
- More Like This.
- Do Not Show Again.
- Reset learned preferences.

### 23.11 Avoid Creepy Personalization

The product should focus on work objects and workflows, not invasive personal behavior inference.

Do not build features such as:

- Employee mood inference.
- Typing-speed judgments.
- Keystroke monitoring.
- Hidden productivity scoring.
- Unnecessary behavioral surveillance.

---

## 24. Team and Collaboration Intelligence

### 24.1 Team Workspace

Possible sections:

- Needs Attention.
- Unassigned Work.
- Overdue.
- Waiting.
- Recent Activity.
- Workload.
- Approvals.
- Important Changes.

### 24.2 Assignment and Ownership

Every shared work item should have clear ownership where appropriate.

### 24.3 Handoff

Handoff should include enough context for the receiving person to continue without reconstructing the situation.

### 24.4 Mentions

Mentions should be actionable and resolvable.

### 24.5 Shared Notes

Team notes must be clearly distinguishable from private notes.

### 24.6 Workload Awareness

Show understandable business workload indicators, not simplistic employee rankings.

Example:

```text
Mary
7 due today
2 blocked

John
2 due today
0 blocked

Possible workload imbalance.

[Review Work]
```

### 24.7 Collaboration Without Surveillance

Team intelligence should focus on:

- Blocked work.
- Unassigned work.
- Overdue work.
- Business exceptions.
- Customer risks.
- Approval delays.
- Workload imbalance.

It should not focus on:

- Keystrokes.
- Mouse activity.
- Minutes online.
- Time spent on every page.

---

## 25. Management and Decision Intelligence

### 25.1 Management Overview

Managers should primarily see:

- Critical issues.
- Important exceptions.
- Decisions required.
- Blocked work.
- Team attention.
- Important changes.
- Emerging risks.

### 25.2 Exceptions First

Normal business should remain quiet.

Example:

```text
EXCEPTIONS

CRITICAL
- Customer complaint unresolved 5 days
- Shipment blocked by approval

IMPORTANT
- Product complaints increasing
- Quotation discount outside normal range
- Contract review overdue
```

### 25.3 Decision Queue

Decision cards should organize evidence rather than merely recommend an answer.

Example:

```text
DISCOUNT DECISION

Requested:
15%

Normal:
<= 10%

Order Value:
$120,000

Customer:
Important; 6-year relationship

Previous Exceptions:
2

Reason:
Customer commits to larger volume.

POSSIBLE IMPACT
Discount value: $18,000

[Approve]
[Reject]
[Request Information]
```

### 25.4 Manager "Why?"

When a metric changes, show associated factors and evidence.

Use careful language such as "associated with" or "contributing factors" unless causation is established.

### 25.5 What Changed?

Managers should be able to ask:

```text
What changed since Friday?
```

and receive grouped important changes across customers, products, operations, approvals, and team work.

### 25.6 Management by Exception

The desired model:

```text
Normal Business -> Quiet
Exceptions -> Visible
Decisions -> Visible
Risks -> Visible
Important Changes -> Visible
```

### 25.7 No Hidden Employee Scoring

Avoid opaque "Productivity Score" systems.

Show understandable business measurements individually.

### 25.8 Sensitive Decisions

For consequential decisions, emphasize:

- Facts.
- Evidence.
- Business context.
- Possible impact.
- Policy/limits.

Avoid framing as "AI recommends approval" unless the business explicitly wants and governs such a feature.

---

## 26. Contextual Assistance

### 26.1 Contextual Assistant Panel

An optional contextual panel may offer:

- About this.
- What changed?
- Related information.
- What needs attention?
- What should I do next?
- Why?
- Ask...

The assistant should know the object currently being viewed.

Examples:

On Product P-102:
- What changed recently?

On Customer ABC:
- What are we waiting for?

On an email:
- What questions do I need to answer?

On a document:
- What changed from the previous version?

On management:
- Why did complaints increase?

### 26.2 Tell Me About This

A universal action should provide a concise summary of:

- What it is.
- What is happening.
- What changed.
- What needs attention.
- What is related.

### 26.3 Why?

The system should support explanations such as:

- Why important?
- Why waiting approval?
- Why can't I edit?
- Why is this in My Work?
- Why overdue?
- Why warning?
- Why suggested?

### 26.4 Next-Step Guidance

Where appropriate, show logical next actions.

### 26.5 Contextual Help

Help should reflect the current screen/object instead of requiring users to search a generic help system.

### 26.6 Teach Me

Optional learning mode may provide step-by-step explanations for less experienced users.

### 26.7 Explain Business Terms

Users may ask for explanations of company/business terminology in context.

---

## 27. Trust, Permissions, and Human Control

### 27.1 Core Rule

> **Intelligence must never give a user access to information or actions that the user would not otherwise be permitted to access.**

Conceptually:

```text
User Permission
-> Accessible Business Information
-> Intelligent Assistance
```

Never:

```text
Intelligent Assistance
-> Restricted Information
```

### 27.2 Permission-Aware Search

Search must not reveal restricted information or, where inappropriate, even the existence/count of restricted records.

### 27.3 Permission-Aware Summaries

Summaries must be generated only from information the viewer is authorized to access.

### 27.4 Permission-Aware Questions

If a question requires inaccessible information:

```text
You do not have access to the information required to answer this question.
```

If supported by company policy, an access-request action may be offered.

### 27.5 Related Information and Timeline Permissions

Related panels, counts, timelines, and summaries must follow the same permission rules as the underlying records.

### 27.6 Why Can't I See This?

Where appropriate, provide a meaningful explanation rather than a generic error.

### 27.7 Why Can't I Do This?

Example:

```text
You cannot approve this quotation.

Reason:
You created it, and company policy requires another authorized reviewer.

[View Approval Status]
```

### 27.8 Intelligence Must Respect Authority

The system should not recommend an action outside the user's permitted authority.

### 27.9 Personal vs Shared Information

Clearly distinguish:

- My Notes.
- Team Notes.
- Personal reminders.
- Shared business information.

### 27.10 Personal Notes Must Remain Personal

Private notes must not silently become team/company knowledge or influence shared recommendations in a way that exposes them.

### 27.11 Source Visibility

Important factual answers should expose their source.

Example:

```text
Current warranty:
24 months

Source:
P-102 Technical Specification
Version 6
Section 8.2

[Open Source]
```

### 27.12 Multiple Sources

When an answer depends on multiple sources, show them.

### 27.13 Conflicting Sources

Example:

```text
CONFLICTING INFORMATION

Customer email:
Delivery requested Oct 20

Quotation:
Delivery Oct 25

Order:
Delivery Oct 25

I cannot determine which date is correct.

[Review Sources]
```

Never quietly choose one without a valid business rule.

### 27.14 Missing Information

Say that information is unavailable or cannot be determined rather than guessing.

### 27.15 Confidence Language

Suggested vocabulary:

- Confirmed.
- Likely.
- Possible.
- Conflicting information.
- Not enough information.

### 27.16 Facts vs Interpretation vs Suggestion

Clearly distinguish:

```text
FACT
Quotation has waited 8 days.

INTERPRETATION
This is longer than the recent 2-4 day response range.

SUGGESTION
Consider following up.
```

### 27.17 Why Am I Seeing This?

Example:

```text
You're seeing this because:
- You own Quotation Q-382.
- Customer response was expected yesterday.
- No response has been recorded.
```

### 27.18 User Rejection and Correction

Intelligent suggestions should support suitable actions such as:

- Accept.
- Not Now.
- Ignore.
- Incorrect.
- Already Handled.
- Choose Another.
- Do Not Suggest Again.

### 27.19 Human Confirmation

For important extracted or inferred information:

```text
Possible new customer address:
150 Main Street

Current CRM:
120 Main Street

[Update Customer]
[Ignore]
```

### 27.20 Needs Confirmation

Important uncertain findings may be shown in context and, only when necessary, in a small Needs Confirmation queue.

### 27.21 Automation Visibility

Automatic actions must be visible and explainable.

### 27.22 Action Risk Levels

Conceptually:

**Low Risk**
- Prepare report.
- Generate summary.
- Save draft.
- Group notifications.

**Medium Risk**
- Prepare email.
- Create suggested task.
- Prepare follow-up.
- Suggest data correction.

**High Risk**
- Send external communication.
- Approve transaction.
- Delete important records.
- Change important business data.
- Commit financial terms.
- Change permissions.

Exact classification should be refined by business area.

### 27.23 Preview Before Important Changes

Show:

- Current value.
- New value.
- Source.
- Affected objects.
- Consequences.

### 27.24 Preserve History

Never silently rewrite historical business facts as if the previous state never existed.

### 27.25 Destructive Actions

Important destructive actions should show impact and require appropriate confirmation.

### 27.26 Smart Assistance Preferences

Users may control suitable assistance categories without being overwhelmed by hundreds of settings.

### 27.27 Organization vs User Preferences

Distinguish:

- **Company Policy** - user cannot override.
- **Team Default** - limited customization.
- **Personal Preference** - user controls.

### 27.28 Graceful Failure

If intelligent assistance fails, the normal business feature must remain usable.

---

## 28. Warning Design

### 28.1 Warning Levels

Use consistent levels:

- **Information** - no interruption.
- **Caution** - visible but easy to continue.
- **Warning** - explicit review recommended.
- **Critical** - strong confirmation required.

Use Critical sparingly.

### 28.2 Specific Warnings

Bad:

```text
Security warning.
```

Better:

```text
EXTERNAL RECIPIENT

You are sending:
internal-pricing.xlsx

to:
john@abc.com

This file is marked Internal.

[Remove Attachment]
[Review]
[Send Anyway]
```

### 28.3 Avoid Warning Fatigue

Warnings should appear only when the risk justifies interruption.

---

## 29. Cross-Site Intelligence and Data Freshness UX

### 29.1 Business Context

The Inner Site and Outer Site are isolated from each other at the network level. Information moves between them through an authorized cross-site exchange process rather than normal live network communication.

This specification intentionally does not define the technical exchange implementation.

The UX must therefore distinguish:

> **What this site currently knows** from **what may exist on the other site but has not arrived yet.**

### 29.2 Freshness as a First-Class Business Concept

Important screens should be able to show:

```text
Information current as of:
Oct 6, 08:42

Last cross-site update received:
42 minutes ago
```

### 29.3 Freshness States

Suggested user-facing states:

- **Current** - latest expected information is available.
- **Recent** - information is recent, but newer data may arrive.
- **Delayed** - expected cross-site updates have not arrived.
- **Unknown** - freshness cannot be determined confidently.

### 29.4 Contextual Freshness

Do not show large warnings everywhere.

Normal browsing may show:

```text
Updated 42 min ago
```

A consequential decision may show:

```text
CHECK FRESHNESS

This decision uses information last received from the other site yesterday at 16:20.

A newer update may exist.

[Continue]
[Review Freshness]
```

### 29.5 Local vs Cross-Site Information

Where useful:

```text
LOCAL INFORMATION
Updated 5 minutes ago

CROSS-SITE INFORMATION
Last received 42 minutes ago
```

### 29.6 Last Known

If freshness matters, use:

```text
Last known status:
Approved
```

instead of implying the status is globally current.

### 29.7 Pending Cross-Site Changes

Example:

```text
PENDING CROSS-SITE UPDATE

Customer address changed locally.

Current local value:
150 Main Street

Other site:
Update not yet confirmed
```

### 29.8 Do Not Say "Synchronized" Too Early

Use precise business states and only claim confirmation when it is actually known.

### 29.9 Cross-Site Delivery States

Possible user-facing progression:

- Prepared.
- Queued for Exchange.
- Transferred.
- Received.
- Processed.
- Completed.

Only show states the system can genuinely confirm.

### 29.10 "Sent" Must Be Precise

Example:

```text
SENT FROM YOUR WORKSPACE

Waiting for transfer to Inner Site.
```

Later:

```text
Received by Inner Site.
```

### 29.11 Cross-Site Waiting

Waiting should support:

- Waiting for Cross-Site Transfer.
- Waiting for Processing.
- Waiting for Person after transfer completes.

### 29.12 Distinguish Transfer Delay From Human Delay

Example:

```text
WAITING

Stage:
Cross-site transfer

Human action:
Not started yet
```

versus:

```text
WAITING

Stage:
Manager approval

Transfer:
Completed
```

### 29.13 Do Not Blame a Person Before Delivery

Do not say:

```text
Mary has not approved for 4 hours.
```

when Mary has not yet received the request.

### 29.14 Waiting Since

Where relevant, show:

- Created.
- Received by other site.
- Waiting for person since.

### 29.15 Delayed Transfer

If transfer takes unusually long:

```text
TRANSFER DELAYED

Waiting:
4 hours

Typical:
30-90 minutes

[Review Status]
```

### 29.16 Cross-Site Exceptions

Transfer/processing problems should become business exceptions only when they are abnormal or have business impact.

### 29.17 Freshness in My Work

If a newer response may exist elsewhere, an action item should say so.

### 29.18 Freshness in Smart Inbox

Conversations may show last cross-site update time and warn when message availability may be incomplete.

### 29.19 Freshness in Customer/Product Views

Important "Right Now" information should expose relevant freshness.

### 29.20 Freshness in Search

Sensitive values in search results may include their update time.

Search must avoid implying global completeness when only locally available information is known.

### 29.21 Freshness in Everything About...

Example:

```text
EVERYTHING ABOUT ABC CORPORATION

Current situation:
Based on data available as of 08:42.
```

### 29.22 Freshness in Ask My System

Question:

```text
Has ABC Corporation replied?
```

Appropriate answer:

```text
No reply is available in this site's data as of the last cross-site update at 08:42.

A newer reply may still be awaiting transfer.
```

### 29.23 Freshness-Aware Summaries

Important summaries should state their data scope when necessary.

### 29.24 Freshness-Aware Recommendations

Do not make strong recommendations from significantly stale information.

Example:

```text
FOLLOW-UP MAY BE DUE

No response is available locally for 7 days.

However, cross-site information has not been updated since yesterday.

[Review]
```

### 29.25 Suppress Recommendations When Necessary

When data is too stale, it may be better to wait for fresh information than create a false alarm.

### 29.26 Pending Cross-Site View

Users should be able to see important work awaiting exchange.

### 29.27 Recently Received

A complementary view may show recently received cross-site updates.

### 29.28 Cross-Site Digest

Example:

```text
CROSS-SITE UPDATE

18 new items received.

Important:
2 need your attention.

Other:
16 updates

[Review Important]
```

### 29.29 Cross-Site Morning Briefing

Overnight updates may be summarized into relevant actions, approvals, product changes, and document updates.

### 29.30 Cross-Site Conflict Detection

If both sites changed the same information independently:

```text
CONFLICT DETECTED

Customer ABC
Phone number

Local:
+1 555 1111

Received:
+1 555 2222

[Review]
```

### 29.31 Conflict Review

Show:

- Local value.
- Local author/time.
- Received value.
- Received author/time.
- Choice of current value.
- Option to enter another value.

### 29.32 Conflict as Action Required

Meaningful conflicts should appear in My Work.

### 29.33 Not Every Difference Is a Conflict

Independent compatible changes should not unnecessarily alarm users.

### 29.34 Version Awareness

For documents:

```text
Local version:
v5

Latest received:
v6
```

### 29.35 New Version Notification

When a newer version arrives:

```text
NEW CROSS-SITE VERSION

Specification v6

Important changes:
- Temperature range changed
- Warranty section updated

[Compare]
```

### 29.36 Cross-Site Approval Status

Show whether an approval is:

- Waiting for transfer.
- Received by other site.
- Waiting for approver.
- Approved/rejected.
- Received back locally.

### 29.37 Conditional Follow-Up

A follow-up timer should, where appropriate, begin when the recipient side actually receives the request rather than when the originating user created it.

### 29.38 Cross-Site Conversation Timeline

Example:

```text
Oct 3 10:15
You sent message.

Oct 3 11:05
Transferred to Inner Site.

Oct 3 11:12
Received by Inner Site.

Oct 3 15:42
Mary replied.

Oct 3 16:18
Reply received on Outer Site.
```

### 29.39 What Happened While I Was Waiting?

Provide a concise history of transfer, review, response, and receipt.

### 29.40 Continue When Ready

Example:

```text
[Continue When Ready]
```

Meaning:

> When the expected approval/reply arrives, put this back in My Work.

### 29.41 Notify Me When It Arrives

Users should not need to repeatedly check for cross-site information.

### 29.42 Freshness + Authority

The newest information is not always the most authoritative.

The system should consider both:

- Freshness.
- Authority.

Example:

- Approved procedure from yesterday may be more authoritative than an informal note from today.

### 29.43 Cross-Site Management Overview

Managers may see:

- Pending transfers.
- Delayed items.
- Conflicts requiring review.
- Important received updates.
- Business impact of delays.

Business impact should be more prominent than technical exchange statistics.

### 29.44 Reports and Freshness

Reports should distinguish:

- Reporting period/snapshot.
- Data freshness.

Example:

```text
Data Coverage

Local:
Through 10:00

Cross-site:
Through 08:42
```

### 29.45 Refresh Behavior

If new data depends on the next exchange, repeatedly clicking Refresh should not imply that the system can retrieve it immediately.

Show:

```text
No newer cross-site update is available yet.

Last received:
08:42
```

### 29.46 Five Cross-Site User States

A useful standard vocabulary:

- **Known** - information available here.
- **Pending** - local work waiting to leave this site.
- **Received** - new information arrived from the other site.
- **Stale** - available information may no longer be current.
- **Conflict** - incompatible versions require review.

### 29.47 Cross-Site UX Principles

1. Never imply real-time information when it is not real-time.
2. Show "last known" or "as of" when freshness matters.
3. Distinguish transfer delay from human delay.
4. Do not blame a person before their site has received the request.
5. Do not make strong recommendations from significantly stale data.
6. Surface real conflicts; do not silently overwrite them.
7. Pending cross-site work must remain visible.
8. Users should be able to ask to be notified when information arrives.
9. Freshness indicators should be strongest near high-impact decisions and quiet elsewhere.
10. Business meaning should remain primary; technical exchange details belong in diagnostic views.

---

## 30. Global User Experience

### 30.1 Global Header

Suggested structure:

```text
Company System

[Search or ask anything...]            Notifications   User

My Work | Email | CRM | Product | Documents | Reports | More
```

### 30.2 Global Quick Create

A universal create action may offer:

- Email.
- Task.
- Reminder.
- Note.
- Customer.
- Quotation.
- Document.

Options should reflect user permissions.

### 30.3 Personal Menu

Possible items:

- My Workspace.
- Favorites.
- Recent.
- Drafts.
- Saved Searches.
- My Automations.
- Preferences.

### 30.4 Consistent Actions

Where applicable, use the same language everywhere:

- Favorite.
- Watch.
- Remind.
- Follow Up.
- Task.
- Note.
- Related.
- History.
- Activity.

### 30.5 Empty States

Empty screens should guide users rather than merely say "No records."

### 30.6 Processing States

When an intelligent operation takes time, explain what is happening.

If confidence is insufficient, say so rather than pretending certainty.

### 30.7 Original View

Summarized/transformed content should provide easy access to originals.

Examples:

- Summary / Original.
- Changes / Original Documents.
- Conversation Summary / Full Conversation.

---

## 31. Functional Maturity Model

The product can be understood through five maturity levels.

### Level 1 - Convenience

Examples:

- Favorites.
- History.
- Shortcuts.
- Drafts.
- Saved searches.

### Level 2 - Awareness

Examples:

- Changes.
- Reminders.
- Watches.
- Timelines.
- Notifications.
- Waiting.

### Level 3 - Assistance

Examples:

- Summaries.
- Suggestions.
- Related information.
- Task/action extraction.
- Document understanding.

### Level 4 - Proactive Assistance

Examples:

- Follow-up detection.
- Overdue warnings.
- Priority recommendations.
- Daily briefings.
- Exception detection.
- Routine preparation.

### Level 5 - Conversational / Organizational Intelligence

Examples:

- Ask My System.
- Natural-language business questions.
- Organizational memory.
- Advanced management intelligence.

The product should conceptually establish Levels 1-4 before depending heavily on Level 5.

---

# 32. Product Roadmap

## 32.1 Version 1 - Personal Productivity Foundation

### Goal

> **Make the existing system substantially easier and faster to use.**

Primary capabilities:

- My Work foundation.
- Personal Tasks.
- Reminders.
- Favorites.
- Recent Items.
- Continue Working.
- Draft Recovery.
- Personal Notes.
- Quick Actions.
- Saved Searches.
- Saved Grid Views.
- Basic Universal Search.
- Quick Preview.
- Related Information.
- Human-readable Activity.
- Basic Waiting.
- Basic Follow-Up.
- Better notification categories.
- Better empty states.
- Better contextual errors.

### Expected User Experience

> "The system remembers where I was, lets me find things faster, keeps my work together, and requires fewer clicks."

### V1 Should Not Depend On

- Advanced conversational assistant.
- Automatic email understanding.
- Predictive analytics.
- Advanced recommendations.
- Automatic business decisions.
- Complex user-created automation.
- Organizational knowledge intelligence.
- Trend prediction.
- Expert finding.
- Advanced management intelligence.

---

## 32.2 Version 2 - Work Awareness & Coordination

### Goal

> **Make the system understand work state, waiting, changes, and team coordination.**

Primary capabilities:

- Action Required.
- Why Me?
- Why Now?
- Advanced Waiting.
- Waiting Resolved.
- People Waiting on Me.
- Watch.
- What Changed?
- Since You Last Viewed.
- Important vs All Changes.
- Assignment.
- Handoff.
- Mentions.
- Team Notes.
- Work Threads.
- Process View.
- Where Is This Stuck?
- Cross-site status UX.
- Cross-site waiting.
- Freshness.
- Conflict review.
- Team Overview.

### Expected User Experience

> "The system knows what needs action, what I'm waiting for, who is waiting for me, what changed, and how my work connects with other people."

---

## 32.3 Version 3 - Intelligent Assistance

### Goal

> **Understand business content and help users interpret it.**

Primary capabilities:

- Email summaries.
- Question extraction.
- Action extraction.
- Deadline detection.
- Commitment detection.
- Commitments from others.
- Smart reply checklist.
- Writing assistance.
- Document summaries.
- Document Q&A.
- Document comparison.
- Customer summaries.
- Tell Me About This.
- Duplicate detection.
- Unusual-value detection.
- Data conflict detection.
- Impact analysis.
- Company knowledge.
- Similar cases.
- Limited source-grounded Ask My System.

### Expected User Experience

> "The system understands emails, documents, business context, and changes well enough to help me understand what is happening."

---

## 32.4 Version 4 - Proactive Intelligence & Automation

### Goal

> **Notice problems and prepare work before the user asks.**

Primary capabilities:

- Proactive follow-up.
- Stalled work detection.
- Deadline risk.
- Forgotten work.
- Missing-step detection.
- Routine detection.
- Automatic preparation.
- Exception Inbox.
- Next Best Action.
- What Should I Do Next?
- Personal automation rules.
- Automation history.
- Automation control.
- Intelligent interruption/prioritization.
- Freshness-aware recommendations.
- Conditional cross-site follow-ups.

### Expected User Experience

> "The system notices forgotten work, warns me before problems happen, prepares repetitive work, and helps me decide what to do next."

---

## 32.5 Version 5 - Organizational Intelligence

### Goal

> **Help the organization remember, learn, coordinate, and make better decisions.**

Primary capabilities:

- Organizational Memory.
- Has This Happened Before?
- Lessons Learned.
- Decision Memory.
- Knowledge Gap detection.
- Expert Finder.
- Management by Exception.
- Trend Detection.
- Early Warning.
- Management Why?
- Management What Needs Me?
- Advanced source-grounded business Q&A.
- Cross-business insights.

### Expected User Experience

> "The organization can reuse its history, decisions, knowledge, and experience to make future work easier and better."

---

## 33. Feature Evolution

Features should mature rather than be replaced.

### 33.1 Follow-Up

```text
V1 - User manually creates follow-up.
V2 - Follow-up understands Waiting.
V3 - System detects possible follow-up from communication.
V4 - System proactively recommends follow-up.
V5 - System considers broader relationship and historical patterns.
```

### 33.2 Search

```text
V1 - Keyword / ID search.
V2 - Cross-module search + context.
V3 - Meaning-based search.
V4 - Proactive related information.
V5 - Organization-wide knowledge questions.
```

### 33.3 My Work

```text
V1 - Tasks, reminders, manual waiting, assigned work.
V2 - Action Required, advanced waiting, mentions, changes.
V3 - Detected actions, deadlines, commitments, intelligent context.
V4 - Risk, stalled work, forgotten work, Next Best Action.
V5 - Organizational and management intelligence.
```

### 33.4 Customer View

```text
V1 - Related information, activity, tasks, notes, favorites.
V2 - Attention, Right Now, Waiting, What Changed, Watch.
V3 - Customer Summary, communication understanding, impact, knowledge.
V4 - Suggested Next Action, relationship reminders, risk detection.
V5 - Historical patterns, similar cases, strategic insights.
```

### 33.5 Smart Inbox

```text
V1 - Better filters, tasks, reminders, follow-up, related customer.
V2 - Needs Reply, Waiting, conversation state, assignment.
V3 - Summary, questions, commitments, deadlines, reply checklist, draft help.
V4 - Proactive follow-up, forgotten request detection, priority intelligence.
V5 - Organizational communication knowledge.
```

### 33.6 Cross-Site UX

```text
V1 - Basic transfer status.
V2 - Freshness, waiting, pending, received, conflict.
V3 - Freshness-aware summaries and answers.
V4 - Freshness-aware recommendations and delayed-transfer exceptions.
V5 - Cross-site organizational intelligence.
```

---

## 34. Human Control Across the Roadmap

Human control must remain clear at every stage.

```text
V1 - User controls work.
V2 - User controls coordination.
V3 - User confirms intelligent suggestions.
V4 - User controls automation.
V5 - Humans remain accountable for consequential decisions.
```

More intelligence must not mean less clarity about responsibility.

---

## 35. Screen-Level Reference Designs

### 35.1 My Work

```text
MY WORK
Tuesday, October 6

Need Action        4
Waiting            5
Reminders          3
Important Changes  2

TODAY

ACTION REQUIRED
ABC Corporation
Customer needs a reply
Waiting: 3 days
Due: Today

[Reply] [Open] [Later]

PRODUCT P-102
Review specification v6
Due: Today

[Review] [Delegate] [Later]

WAITING
Quotation Q-382
Waiting for customer approval
Waiting: 5 days

[Follow Up] [Open]
```

### 35.2 Smart Inbox

```text
INBOX

Needs Reply     6
Waiting         8
Follow-Up       3
Important       4
Unread         21

ATTENTION   FROM        SUBJECT          STATUS
Action      ABC Corp    Delivery date    Needs Reply
Important   Mary        Product P-102    Action
Waiting     XYZ Corp    Quotation Q-183  Waiting
-           John        Meeting notes    Information
```

### 35.3 Customer Smart View

```text
ABC CORPORATION
Important Customer - Active

[Email] [Task] [Note] [Follow Up]

ATTENTION
Latest email needs reply - 3 days
Contract review - Due today

RIGHT NOW
Quotation Q-382 - Waiting for customer
Orders - 3 active
Issues - 1 open
Contract - Under review

RECENT ACTIVITY
...

PEOPLE
...

RELATED
Emails / Orders / Documents / Products / Tasks / Notes
```

### 35.4 Product Smart View

```text
PRODUCT P-102
Active

Current Price: $135

ATTENTION
Specification v6 requires review.

IMPORTANT CHANGE
Price: $120 -> $135

IMPACT
3 open quotations use previous price.
14 active customers use this product.

[Review Impact]

KNOWN ISSUES
...

RELATED
Customers / Orders / Quotations / Documents / Emails
```

### 35.5 Universal Search

```text
Search: ABC

BEST MATCH
ABC Corporation - Customer

PEOPLE
John Smith - ABC Corporation
Mary Brown - ABC Corporation

BUSINESS
Quotation Q-382
Order O-391

COMMUNICATION
184 emails

DOCUMENTS
23 documents

[Everything About ABC Corporation]
```

### 35.6 Management Overview

```text
MANAGEMENT OVERVIEW

NEEDS ATTENTION
Critical Issues          3
Important Exceptions     8
Decisions Required       6

BLOCKED
2 Quotations
1 Shipment
3 Contracts

TEAM
4 Overdue
3 Blocked
2 Unassigned

IMPORTANT CHANGES
Product P-102 price changed.
ABC contract updated.
XYZ complaint reopened.
```

---

## 36. Success Criteria by Product Stage

### 36.1 V1 Success

Can users:

- Get to work faster?
- Lose less unfinished work?
- Remember follow-ups?
- Find information more easily?
- Navigate less?
- Reuse saved views and shortcuts?

### 36.2 V2 Success

Can users clearly see:

- What needs action?
- What they are waiting for?
- Who is waiting for them?
- What changed?
- Where work is stuck?
- What is pending across sites?

### 36.3 V3 Success

Can the system help users understand:

- Communication.
- Documents.
- Changes.
- Relationships.
- Business impact.

Can it do so without hiding original evidence?

### 36.4 V4 Success

Can the system:

- Notice likely problems early?
- Surface forgotten work?
- Prepare repetitive work?
- Reduce manual checking?
- Avoid excessive false alarms?

### 36.5 V5 Success

Can the organization:

- Reuse previous decisions?
- Reuse resolved cases?
- Preserve lessons learned?
- Find relevant expertise?
- Recognize trends?
- Make organizational knowledge available in context?

---

## 37. Product Quality Rules

### 37.1 Later Intelligence Must Not Make Simple Work Harder

Even in advanced versions:

- Create Task remains simple.
- Search by ID remains simple.
- Original email remains available.
- Ignore Suggestion remains possible.
- Manual workflows remain possible where policy permits.

### 37.2 Measure Noise, Not Just Intelligence

Desired direction:

```text
More useful attention
+
Fewer irrelevant interruptions
```

Not:

```text
More intelligence
=
More alerts
```

### 37.3 Confidence Must Be Honest

Never hide uncertainty behind confident language.

### 37.4 Business Meaning Before Technical Detail

Business users should see:

```text
Waiting for Inner Site
```

rather than implementation-oriented status codes.

Diagnostic detail may exist separately for authorized support/administrative users.

### 37.5 History Matters

Important business history must be preserved rather than overwritten.

### 37.6 Source Matters

Important answers should be verifiable.

### 37.7 Freshness Matters

Information age is part of business trust, especially in the cross-site environment.

### 37.8 Authority Matters

Newer information is not automatically more authoritative than approved information.

---

## 38. Consolidated Trust Principles

1. Permission before intelligence.
2. No information leakage through summaries, search, related items, or answers.
3. Facts, interpretations, and suggestions must be distinguishable.
4. Important answers should expose their sources.
5. Uncertainty and conflicts must be visible.
6. Important business changes require appropriate human confirmation.
7. Automation must be transparent and controllable.
8. Personal information remains personal unless explicitly shared.
9. Intelligence should assist managers, not create hidden employee surveillance.
10. Users can correct, dismiss, or override suggestions.
11. Original authoritative information remains accessible.
12. The normal business system must continue working when intelligent assistance cannot help.
13. Freshness must be visible when it materially affects trust.
14. Do not imply real-time information in a delayed cross-site environment.
15. Do not blame a person for delay before the work has reached them.
16. Humans remain accountable for consequential business decisions.

---

## 39. Consolidated Product Pillars

The complete Intelligent Workspace can be understood through these pillars:

### 1. My Work
**Question:** What requires me?

### 2. Find
**Question:** Where is it?

### 3. Context
**Question:** What should I know about this?

### 4. Impact
**Question:** What will this affect?

### 5. Communication Intelligence
**Question:** What does this communication mean, and what should I do?

### 6. Knowledge & Document Intelligence
**Question:** What does our company already know?

### 7. Automation & Proactive Assistance
**Question:** What should the system notice, prepare, or suggest before I search?

### 8. Personalization & User Convenience
**Question:** How can the system adapt to the way each employee actually works?

### 9. Team & Collaboration Intelligence
**Question:** How can people coordinate work with less manual checking and handoff friction?

### 10. Management & Decision Intelligence
**Question:** What changed, what is unusual, what is blocked, and what requires management intervention?

### Cross-Cutting Foundation: Trust & User Control
**Question:** Can the user understand, verify, control, and safely rely on the assistance?

### Cross-Cutting Foundation: Cross-Site Freshness
**Question:** How current is the information, what is pending, and what may not have arrived yet?

---

## 40. Final Product Model

```text
                       INTELLIGENT WORKSPACE

                           MY WORK
                              |
        +---------------------+---------------------+
        |                     |                     |
       FIND                 CONTEXT               IMPACT
        |                     |                     |
        +---------------------+---------------------+
                              |
                       COMMUNICATION
                              |
                          KNOWLEDGE
                              |
                         AUTOMATION
                              |
                      PERSONALIZATION
                              |
                       COLLABORATION
                              |
                        MANAGEMENT

                  TRUST & USER CONTROL
                  CROSS-SITE FRESHNESS
```

Underlying concepts:

```text
Attention
Action Required
Task
Follow-Up
Waiting
Reminder
Watch
Notification
Change
Decision
Exception
Ownership
Deadline
Priority
Context
Relationship
History
Knowledge
Impact
Freshness
Authority
Confidence
```

---

## 41. Final Product Philosophy

The Intelligent Workspace should not be judged by how many "AI features" it contains.

It should be judged by whether employees:

- Remember less manually.
- Search less manually.
- Repeat fewer operations.
- Understand business situations faster.
- Coordinate work more clearly.
- Miss fewer important changes.
- Make fewer avoidable mistakes.
- Spend less time checking whether something happened.
- Retain control over consequential actions.

The final guiding principle is:

> **The system should remember, organize, connect, explain, warn, prepare, and assist - while the user remains informed and in control.**

---

## 42. Scope Boundary for the Next Stage

This document intentionally stops before technical architecture.

The following topics should be treated as a separate future design stage:

- Application architecture.
- Data model.
- Event model.
- APIs.
- Search implementation.
- Synchronization implementation.
- AI/LLM selection.
- Model hosting.
- Retrieval mechanisms.
- Permissions implementation.
- Automation engine implementation.
- Database changes.
- Background workers.
- Cross-site exchange package format.
- Security implementation.
- Deployment and infrastructure.

The approved functional specification should serve as the baseline for those later technical decisions.

---

**End of Functional Requirements & Product Specification**
