# Microsoft Word 2016/2021: Creating Professional Code Blocks

## 1. Purpose

This guide explains how to format source code professionally in **Microsoft Word 2016 and Word 2021 for Windows**.

The recommended approach is to create a reusable Word paragraph style named:

```text
Code
```

Once the style is created, you can select any code snippet and apply the **Code** style instead of manually formatting every snippet.

---

# 2. Why Use a Word Paragraph Style?

If you have many code snippets, manually applying formatting every time is inconvenient.

For example, without a style you might repeatedly have to set:

- Font: Consolas
- Font size: 9 pt
- Alignment: Left
- Indentation
- Line spacing
- Paragraph spacing
- Background shading
- Border

A Word style stores all of these settings together.

The workflow becomes:

```text
Select code
     |
     v
Apply "Code" style
     |
     v
All code formatting is applied automatically
```

This is especially useful for technical documents containing:

- PHP
- JavaScript
- React
- JSON
- HTML
- CSS
- PowerShell
- SQL
- Browser-extension code
- Configuration examples
- Command-line output

---

# 3. Recommended Code-Block Appearance

A professional code block can use:

```text
Font:            Consolas
Font size:       9 pt
Alignment:       Left
Line spacing:    Single
Left indent:     0.25"
Before spacing:  6 pt
After spacing:   6 pt
Background:      Light gray
Border:          Thin, optional
```

Example:

```javascript
const channel = new BroadcastChannel("auth");

channel.onmessage = (event) => {
    console.log(event.data);
};
```

The code should remain normal Word text rather than an image so that readers can select and copy it.

---

# 4. Step 1 — Open the Styles Pane

There are two ways to open the Styles pane.

## Method A — Keyboard shortcut

Press:

```text
Ctrl + Alt + Shift + S
```

The Styles pane appears on the right side of Word.

It may look approximately like:

```text
+---------------------------+
| Styles                    |
+---------------------------+
| Normal                    |
| No Spacing                |
| Heading 1                 |
| Heading 2                 |
| Heading 3                 |
| ...                       |
+---------------------------+
```

## Method B — Home tab

Go to:

```text
Home
  |
  +-- Styles
```

In the lower-right corner of the Styles group, click the small **dialog launcher** button.

The Styles pane opens.

---

# 5. Step 2 — Create a New Style

At the bottom of the Styles pane, select:

```text
New Style
```

Depending on your Word configuration, the button may appear as an icon containing an **A** and a plus sign.

Word opens a dialog similar to:

```text
Create New Style from Formatting
```

---

# 6. Step 3 — Name the Style

In the **Name** field, enter:

```text
Code
```

The important settings should now start with:

```text
Name: Code
```

Use a short, descriptive name because you will use this style frequently.

---

# 7. Step 4 — Set Style Type to Paragraph

Find:

```text
Style type:
```

Select:

```text
Paragraph
```

This is important for multi-line code.

A paragraph style allows Word to apply formatting to the entire code block.

Use:

```text
Style type: Paragraph
```

rather than creating only a character-level formatting definition.

---

# 8. Step 5 — Select a Monospace Font

Source code is easier to read with a monospace font.

Recommended:

```text
Font: Consolas
```

Recommended size:

```text
9 pt
```

You can use:

```text
10 pt
```

if you want larger code.

### Why Consolas?

In a monospace font, every character has approximately the same width.

This makes code easier to read:

```text
if (condition) {
    doSomething();
}
```

and makes indentation visually consistent.

Other possible monospace fonts include:

- Cascadia Mono
- Courier New
- Lucida Console

For Windows technical documentation, **Consolas** is a good default.

---

# 9. Step 6 — Set Alignment

Set:

```text
Alignment: Left
```

Do not use justified alignment for source code.

Code should preserve its natural horizontal structure.

---

# 10. Step 7 — Configure Paragraph Formatting

In the Create New Style dialog, select:

```text
Format
  |
  +-- Paragraph...
```

The Paragraph dialog opens.

---

# 11. Indentation Settings

Under **Indentation**, use:

```text
Left:       0.25"
Right:      0"
Special:    None
```

The left indentation creates visual separation between normal text and code.

For example:

```text
Normal paragraph starts here.

    Code starts here.
    Code continues here.
```

You can increase the left indentation to:

```text
0.3"
```

or:

```text
0.4"
```

if you prefer more separation.

---

# 12. Spacing Settings

Under **Spacing**, use:

```text
Before: 6 pt
After:  6 pt
```

This creates a small amount of space before and after the code block.

For example:

```text
Normal paragraph

[6 pt]

+----------------------------------+
| Code                             |
| Code                             |
+----------------------------------+

[6 pt]

Normal paragraph
```

---

# 13. Line Spacing

Set:

```text
Line spacing: Single
```

Code usually does not need the larger line spacing used in some normal documents.

---

# 14. Add Background Shading

A light background makes a code block visually distinct.

From the style dialog, select:

```text
Format
  |
  +-- Borders...
```

The **Borders and Shading** dialog opens.

Select:

```text
Shading
```

Under:

```text
Fill
```

choose a light gray.

A very light gray is recommended.

Avoid a dark background unless you specifically want a dark-theme document.

---

# 15. Add a Border

A border is optional.

If you want one, select:

```text
Borders
```

Then:

```text
Setting: Box
```

A thin border is usually sufficient.

For example:

```text
Setting: Box
Width:   0.5 pt
```

A code block can then look conceptually like:

```text
+------------------------------------------------+
| const channel = new BroadcastChannel("auth");  |
|                                                |
| channel.onmessage = (event) => {               |
|     console.log(event.data);                  |
| };                                             |
+------------------------------------------------+
```

A border is not required. A light gray background without a border often looks cleaner.

---

# 16. Keep Code Lines Together

Open:

```text
Format
  |
  +-- Paragraph...
  |
  +-- Line and Page Breaks
```

Consider enabling:

```text
Keep lines together
```

This helps prevent a small code block from being split unnecessarily.

### Important

Do not force very large code blocks to remain on one page.

For example, a 100-line code block may need to span multiple pages.

Otherwise Word may create a large blank area at the bottom of a page.

---

# 17. Recommended Complete Code Style

A good starting configuration is:

| Property | Value |
|---|---|
| Style name | `Code` |
| Style type | Paragraph |
| Font | Consolas |
| Font size | 9 pt |
| Alignment | Left |
| Left indentation | 0.25" |
| Right indentation | 0" |
| Special indentation | None |
| Before spacing | 6 pt |
| After spacing | 6 pt |
| Line spacing | Single |
| Background | Light gray |
| Border | 0.5 pt, optional |
| Keep lines together | Yes |

---

# 18. Save the Style

After configuring all settings:

1. Click **OK** in the Paragraph dialog.
2. Configure Borders and Shading.
3. Click **OK**.
4. Click **OK** in the Create New Style dialog.

The new style should appear in the Styles pane:

```text
Styles
----------------
Normal
Heading 1
Heading 2
Heading 3
Code
```

You now have a reusable code style.

---

# 19. Applying the Code Style

Suppose you have this JavaScript:

```javascript
const channel = new BroadcastChannel("auth");

channel.onmessage = (event) => {
    console.log(event.data);
};
```

Paste the code into Word.

Then:

1. Select the entire code block.
2. Go to **Home**.
3. Open **Styles**.
4. Select **Code**.

Word applies the complete code formatting automatically.

---

# 20. Applying the Style From the Styles Pane

If the Styles pane is open:

```text
Select code
     |
     v
Click "Code"
```

The code immediately receives the formatting.

This is much faster than manually changing:

```text
Font
Size
Spacing
Indentation
Shading
Border
```

every time.

---

# 21. Modifying the Code Style Later

One of the biggest advantages of using a style is that you can change the formatting globally.

For example, suppose you initially use:

```text
Consolas 9 pt
```

but later decide that:

```text
Cascadia Mono 9.5 pt
```

looks better.

You can modify the **Code** style instead of changing every code block manually.

In the Styles pane:

1. Right-click **Code**.
2. Select **Modify...**
3. Change the formatting.
4. Click **OK**.

Existing paragraphs using the Code style will update automatically.

---

# 22. Making the Style Available in Future Documents

When creating or modifying the style, Word may provide an option similar to:

```text
Only in this document
```

or:

```text
New documents based on this template
```

If you want the Code style to be available in future documents based on the same template, choose the template-related option.

For a reusable technical-document template, this is particularly useful.

---

# 23. Code Style vs Normal Text

Do not format all text using the Code style.

Use:

```text
Normal
```

for normal paragraphs.

Use:

```text
Code
```

for multi-line source code.

For example:

### Normal paragraph

> The application creates a BroadcastChannel named `auth`.

### Code block

```javascript
const channel = new BroadcastChannel("auth");
```

This creates a clear visual distinction.

---

# 24. Creating an Inline Code Style

For short code names inside normal sentences, a full paragraph style is usually unnecessary.

For example:

> The application calls `signData()` to create the digital signature.

For inline code, a **character style** can be useful.

A possible style:

```text
Name: Code Inline
Type: Character
Font: Consolas
Size: 9 pt
```

You can optionally use a very light shading.

This is different from the main:

```text
Code
```

paragraph style.

---

# 25. Creating a Code Output Style

You can also create a separate style for program output.

For example:

```text
Certificate found.
Signing data...
Signature created successfully.
```

Create:

```text
Code Output
```

Possible settings:

```text
Style type: Paragraph
Font: Consolas
Size: 9 pt
Background: Light gray
Alignment: Left
```

You can use a different background from the source-code style if you want readers to distinguish source code from output.

---

# 26. Recommended Styles for a Technical Document

For a large technical document, a useful set of styles is:

```text
Normal
Heading 1
Heading 2
Heading 3
Code
Code Inline
Code Output
```

The roles are:

| Style | Purpose |
|---|---|
| Normal | Normal explanatory text |
| Heading 1 | Major sections |
| Heading 2 | Subsections |
| Heading 3 | Smaller subsections |
| Code | Multi-line source code |
| Code Inline | Short code names inside sentences |
| Code Output | Console/application output |

---

# 27. Example: PHP

Normal text:

> The PHP function accepts an array of users and returns their names.

Code:

```php
function getNames(array $users): array
{
    $names = [];

    foreach ($users as $user) {
        $names[] = $user->name;
    }

    return $names;
}
```

Apply the **Code** paragraph style to the entire code block.

---

# 28. Example: JavaScript

Normal text:

> The application uses BroadcastChannel to notify other browser tabs when the user logs out.

Code:

```javascript
const channel = new BroadcastChannel("auth");

function logout() {
    localStorage.removeItem("token");

    channel.postMessage({
        type: "logout"
    });
}

channel.onmessage = (event) => {
    if (event.data.type === "logout") {
        window.location.href = "/login";
    }
};
```

Apply the **Code** style.

---

# 29. Example: JSON

```json
{
    "type": "SIGN_REQUEST",
    "certificateId": "12345",
    "data": "..."
}
```

The same **Code** paragraph style can be used.

---

# 30. Example: PowerShell

```powershell
Get-ItemProperty `
    -Path "HKCU:\Software\Example" `
    -Name "Setting"
```

Again, use the **Code** style.

---

# 31. Code Formatting and Syntax Highlighting

Microsoft Word does not provide the same syntax-highlighting experience as a modern code editor such as VS Code.

The Word **Code** style primarily controls document formatting:

- Font
- Size
- Spacing
- Indentation
- Background
- Border

It does not automatically understand PHP, JavaScript, JSON, or other programming languages.

If syntax colors are important, you can paste formatted code from a code editor while retaining the formatting, but this can make document maintenance more complicated.

For formal technical documentation, a consistent monochrome code style is often easier to maintain.

---

# 32. Avoid Using Images for Normal Code

You can insert a screenshot or generated image of code, but this has disadvantages:

- Text cannot be easily copied
- Search does not work well
- Accessibility is worse
- Editing is difficult
- Code may become unreadable when zoomed or printed

For most documentation, keep source code as actual Word text.

Use images only when the visual appearance itself is important.

---

# 33. Recommended Workflow for Technical Documentation

A practical workflow is:

```text
Write normal explanation
        |
        v
Insert code
        |
        v
Select code
        |
        v
Apply "Code" style
        |
        v
Continue normal explanation
```

For example:

```text
The application creates an authentication channel.

[Code block]

The channel is used to notify other browser tabs.
```

This produces a consistent document.

---

# 34. Troubleshooting: Cannot Find Shading

If you cannot find the **Shading** button in Word 2016 or Word 2021:

### Method 1

Select the code and go to:

```text
Home
  -> Paragraph
  -> Borders ▼
  -> Borders and Shading...
  -> Shading
```

### Method 2

Right-click the selected paragraph and look for:

```text
Borders and Shading...
```

If it is not available there, use the **Borders** dropdown in the Home tab.

The **Shading** feature is inside the Borders and Shading dialog.

---

# 35. Troubleshooting: Code Is Being Spell-Checked

Word may underline programming identifiers with red or blue lines.

For example:

```text
BroadcastChannel
signData
certificateId
```

Word may interpret these as spelling or grammar errors.

You can disable proofing for selected code.

One approach is:

1. Select the code.
2. Go to **Review**.
3. Select **Language**.
4. Select **Set Proofing Language**.
5. Enable:

```text
Do not check spelling or grammar
```

This can make code blocks cleaner.

If the document is large, you may prefer to include this setting in your code style/template workflow.

---

# 36. Troubleshooting: Long Lines

Long source-code lines can extend beyond the page.

Possible solutions:

### Option 1 — Use a smaller font

```text
9 pt -> 8.5 pt
```

### Option 2 — Use landscape orientation

For documents containing wide code:

```text
Layout
  -> Orientation
  -> Landscape
```

### Option 3 — Allow Word to wrap lines

This keeps the text readable but may change the visual appearance of the original source.

### Option 4 — Shorten the source example

For documentation, showing the relevant part of the code is often better than inserting an entire source file.

---

# 37. Troubleshooting: Code Splits Across Pages

For a short code block:

```text
Format
  -> Paragraph
  -> Line and Page Breaks
  -> Keep lines together
```

For a large code block, allow page breaks.

Do not use "Keep with next" indiscriminately because it can cause unexpected page layout.

---

# 38. Recommended Final Word Configuration

For a professional technical document in Word 2016 or Word 2021, use:

```text
Normal text
    |
    +-- Normal style

Headings
    |
    +-- Heading 1
    +-- Heading 2
    +-- Heading 3

Code
    |
    +-- Code
    +-- Code Inline
    +-- Code Output
```

Recommended main Code style:

```text
Name:              Code
Type:              Paragraph
Font:              Consolas
Size:              9 pt
Alignment:         Left
Left indent:       0.25"
Right indent:      0"
Before:            6 pt
After:             6 pt
Line spacing:      Single
Background:        Light gray
Border:            0.5 pt, optional
Keep lines together: Yes
```

---

# 39. Quick Reference

## Create the style

```text
Ctrl + Alt + Shift + S
        |
        v
New Style
        |
        v
Name: Code
        |
        v
Style type: Paragraph
        |
        v
Font: Consolas
        |
        v
Size: 9 pt
        |
        v
Format -> Paragraph
        |
        v
Set indentation / spacing
        |
        v
Format -> Borders
        |
        v
Shading -> Light gray
        |
        v
Borders -> Box (optional)
        |
        v
OK
```

## Use the style

```text
Select code
    |
    v
Home -> Styles -> Code
```

---

# 40. Final Recommendation

For Word 2016 and Word 2021, the best approach for a technical document containing PHP, JavaScript, React, JSON, browser-extension code, and configuration examples is to create a reusable **Code** paragraph style.

Use:

```text
Consolas
9 pt
Light gray background
Single line spacing
0.25" left indentation
6 pt before/after
Optional thin border
```

The biggest benefit is consistency.

Once the style is created, every code block can be formatted with one action, and changing the style later updates all code blocks that use it.
