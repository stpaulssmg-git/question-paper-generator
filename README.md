# St. Paul's Question Paper Generator

A lightweight, single-file PHP web application for schools and educators to manage Excel (`.xlsx`) question banks, create and organize examination papers, preview the final layout, save and restore question papers, and export professionally formatted Microsoft Word (`.docx`) question papers and answer keys.

The current application is implemented primarily in `index.php` and uses PhpSpreadsheet and PhpWord for Excel and Word document handling. It includes multi-user teacher workspaces, administrator controls, question-bank editing, a question-selection basket, blueprint summaries, configurable paper sections, and protected server-side storage.

---

## ✨ Key Features

### 🔐 Multi-User Authentication & Workspace Isolation

- Built-in **Administrator** and **Teacher** roles.
- User accounts are stored in `data/users.json` with PHP password hashing.
- Every teacher has a separate private workspace for:
  - Question Banks
  - Saved Question Papers
- Administrators can:
  - Create teacher accounts
  - Edit teacher name, username, subject, and password
  - Enable or disable teacher accounts
  - Switch into a teacher's workspace
  - Prepare or manage papers on behalf of a teacher
- Teacher sessions are restricted to their own workspace.
- Changing accounts/workspaces clears previously selected banks, baskets, and paper configuration to prevent cross-account data carry-over.
- Teachers can change their own password from the application.

---

## 📊 Question Bank Management

### Import Existing Excel Question Banks

Upload `.xlsx` question banks directly from the application.

The importer:

- Accepts an Excel workbook with a `Questions` worksheet, or uses the active worksheet when appropriate.
- Automatically detects supported column names within the first five rows.
- Loads questions into the application's **Available Questions** area.
- Starts a fresh Question Paper context when a new Question Bank is imported, preventing an older saved-paper target from being reused accidentally.

### Create a New Question Bank

A new Question Bank can be started from the built-in template.

The first question can be entered through the **Create & Add Questions** interface. When the first question is saved, the application creates the XLSX workbook using the Question Bank template structure.

The standard template fields are:

- QNo
- Chapter
- Category
- Marks
- Difficulty
- Question_Text
- Option_A
- Option_B
- Option_C
- Option_D
- Correct_Option
- Answer_Text

### Add Questions Directly from the Browser

Questions can be created without manually editing Excel.

The application supports:

- Question number
- Chapter
- Category
- Marks
- Difficulty
- Rich question text
- MCQ options A–D
- Correct option
- Answer text

For the metadata fields used by the Question Bank:

- **Chapter, Category, and Difficulty** are normalized to uppercase when saved.
- **Marks** must be a positive numeric value.
- The Create & Add interface provides XLSX-backed autocomplete suggestions for Chapter, Category, Marks, and Difficulty.
- Suggestions are generated from the currently loaded Question Bank.

---

## ✏️ Inline Question Bank Editing

Questions can be edited directly from the **Available Questions** section.

Editable information includes:

- Question text
- Chapter
- Category
- Marks
- Difficulty
- MCQ options A–D
- Other supported answer information

Changes are written back to the active XLSX workbook and the application's current basket is synchronized where applicable.

### Metadata Update Behavior

The application treats shared and question-specific fields differently:

- **Chapter** changes can rename the matching chapter throughout the Question Bank so chapter references remain consistent.
- **Category** changes can update matching category values throughout the Question Bank.
- **Marks** changes apply to the selected question only.
- **Difficulty** changes apply to the selected question only.

This distinction prevents a change to a question-specific value such as Marks or Difficulty from unintentionally modifying unrelated questions.

---

## 🔎 Search & Filtering

The Available Questions area provides multiple ways to locate questions:

- Category
- Marks
- Chapter
- Difficulty
- Free-text search

Search can match:

- Question number
- Question text
- Chapter
- Category
- MCQ options

The question list is paginated and supports live/debounced searching without requiring a complete page reload for every search interaction.

---

## 🧺 Question Basket

Selected questions are collected in a working **Question Paper Basket**.

Supported operations include:

- Add individual questions
- Add multiple questions
- Remove questions
- Edit selected questions
- Clear the complete basket
- Reorder paper sections
- Customize section headings
- Collapse/expand question sections

When questions are added, the interface can automatically move the user's focus toward the paper/preview area.

---

## 📐 Blueprint & Section Management

The Question Paper builder maintains a live blueprint based on the selected questions.

The blueprint provides question and mark information by chapter and section, allowing the teacher to verify the structure of the paper while assembling it.

### Section Ordering

Question sections can be reordered through the interface.

The application supports:

- Custom section order
- Custom section headings
- Collapsible sections
- Automatic background saving of section configuration
- Roman-numeral section numbering in the generated paper

Section mark summaries can represent both uniform and mixed-mark sections, for example:

```text
(1M x 10 = 10)
```

or:

```text
(1M x 5 = 5; 2M x 3 = 6; Total = 11)
```

---

## 📝 Rich Question Editing with CKEditor

Question text supports rich formatting through CKEditor.

Supported formatting includes:

- Bold
- Italic
- Underline
- Strikethrough
- Subscript
- Superscript
- Paragraphs
- Ordered and unordered lists
- Tables
- Embedded images/equation images where supported by the application's sanitization rules

The application sanitizes stored question HTML and removes unsafe event handlers and JavaScript URLs.

Plain-text questions imported from Excel remain supported and are safely rendered.

---

## 👀 Live Question Paper Preview

The application provides a live document-style preview before export.

The preview supports configurable:

- School name
- Paper title
- Class / Subject / Examination information
- Watermark text
- Document font

The preview is designed to resemble the final printed/Word document.

The application can display the overall question/marks information from the paper blueprint rather than relying on a manually entered maximum-mark field.

---

## 📄 Microsoft Word DOCX Export

The **Generate Word DOCX** feature creates a formatted Microsoft Word question paper.

The generated document supports:

- Structured section headings
- Roman-numeral section numbering
- Question numbering
- Rich question formatting
- MCQ options
- Automatic MCQ option layouts
- Section mark summaries
- Watermark text
- Page numbering
- Configurable document fonts
- English and Kannada-friendly Unicode font choices

For MCQs, the exporter automatically chooses an appropriate option layout based on option length, including a single-row layout or a 2×2 arrangement.

---

## 🔑 Answer Key Export

A matching **Generate Answer Key** action is available for the selected Question Paper Basket.

The answer key is generated as a Word (`.docx`) document using the answer information associated with the selected questions.

---

## 🖨️ Browser Print Preview

The application includes a print-specific stylesheet.

From the Question Paper Preview, users can:

- Open the browser's print dialog
- Print directly
- Save the preview as PDF using the browser's PDF printer

The print stylesheet hides application controls and prints the document preview in a clean page-oriented layout.

---

## 💾 Save & Restore Question Papers

Question Papers are saved as JSON snapshots in the active user's private `saved_papers` directory.

### First-Time Save

When saving a new Question Paper:

- The application asks for a filename.
- The filename is stored without an automatically added timestamp.
- If the same filename already exists, a numbered filename is generated rather than silently overwriting another new paper.

### Saving an Already Loaded Paper

If a previously saved Question Paper is loaded and then saved again:

- The application recognizes the loaded paper.
- The same saved file is updated directly.
- A new filename prompt is not required for the normal save operation.

This prevents accidental creation of duplicate Question Papers and keeps the loaded paper as the active save target.

### Restoring a Saved Paper

Loading a saved paper restores its associated Question Paper configuration, including:

- Selected questions
- Sections
- Section order
- Section headings
- Document settings
- Linked Question Bank

The application can automatically load and relink the Question Bank associated with the saved paper.

### Unsaved Basket Protection

When a Question Paper already contains questions, switching to another Question Bank or loading another saved paper triggers a confirmation workflow.

The user can choose to:

- Save and continue
- Discard and continue
- Cancel

This helps prevent accidental loss of the current paper.

---

## 🗂️ Application Workflow

The main workflow is organized into these areas:

### 1. Import / Create Question Bank

- Import an existing XLSX Question Bank, or
- Create a new Question Bank from the built-in template.

### 2. Load / Manage Saved Papers

- Select a previously saved Question Paper.
- Load it.
- Delete it when no longer required.

### 3. Select Question Bank

- Choose the active Question Bank for the current workspace.

### 4. Available Questions

- Search and filter questions.
- Edit Question Bank data.
- Add questions to the basket.
- Create new questions.

### 5. Question Paper Basket / Blueprint

- Review selected questions.
- Organize sections.
- Adjust section order and headings.
- Verify question counts and marks.

### 6. Preview / Export

- Review the final layout.
- Save the Question Paper.
- Generate Word DOCX.
- Generate Answer Key.
- Print the Question Paper.

---

## 📋 Excel Question Bank Format

The importer detects headers case-insensitively and ignores symbols/spaces while matching names.

### Supported Columns

| Field | Supported Column Headers | Required |
|---|---|:---:|
| **Chapter** | `Chapter`, `ChapterName`, `ChapterID` | Yes |
| **Question No.** | `QNo`, `QuestionNo` | Yes |
| **Category / Type** | `Category`, `QuestionType`, `Type` | Yes |
| **Marks** | `Marks`, `Mark`, `Weightage` | Yes |
| **Question Text** | `Question`, `QuestionText`, `QText` | Yes |
| **Difficulty** | `Difficulty`, `DifficultyLevel` | Optional |
| **Option A** | `OptionA` | Optional |
| **Option B** | `OptionB` | Optional |
| **Option C** | `OptionC` | Optional |
| **Option D** | `OptionD` | Optional |
| **Correct Option** | `CorrectOption` | Optional |
| **Answer Text** | `Answer`, `AnswerText` | Optional |

The application scans the first five rows to locate a compatible header row.

### Standard New-Bank Template

New Question Banks use this header structure:

```text
Qno
Chapter
Category
Marks
Difficulty
Question_Text
Option_A
Option_B
Option_C
Option_D
Correct_Option
Answer_Text
```

If the supplied workbook does not match the detected structure, the application also has a fallback mapping for its standard template layout.

---

## 📁 Directory Structure

```text
.
├── index.php                    # Main single-file PHP application
├── composer.json                # Composer dependency configuration
├── vendor/                      # Composer-installed libraries
│
├── 000_NEW_QB_TEMPLATE.xlsx     # Optional new Question Bank template
│
├── data/                        # Auto-created user database
│   ├── users.json
│   └── .htaccess
│
├── question_banks/              # Auto-created private Question Bank storage
│   ├── admin/
│   │   └── *.xlsx
│   └── <teacher_folder>/
│       └── *.xlsx
│
└── saved_papers/                # Auto-created private Question Paper storage
    ├── admin/
    │   └── *.json
    └── <teacher_folder>/
        └── *.json
```

New Question Papers are stored directly in the active user's saved-paper folder. The application also retains compatibility with legacy date-based saved-paper folders.

---

## 🛠️ Requirements

- **PHP:** 8.0 or higher
- **PHP extensions:** `zip`, `xml`, `gd`, and `mbstring`
- **Web server:** Apache is recommended
- **XAMPP / WAMP / LAMP:** Suitable for local or institutional deployment
- **Composer:** Required to install PHP dependencies
- Writable project directories for:
  - `data`
  - `question_banks`
  - `saved_papers`

---

## 📦 Composer Dependencies

The application uses:

- `phpoffice/phpspreadsheet` — Excel workbook reading/writing
- `phpoffice/phpword` — Microsoft Word document generation

Install them with:

```bash
composer require phpoffice/phpspreadsheet phpoffice/phpword
```

If a `composer.json` is already included in the repository, use:

```bash
composer install
```

---

## 🚀 Installation & Setup

### 1. Clone or Copy the Project

Place the project inside your Apache document root.

Example for XAMPP:

```text
C:\xampp\htdocs\qp-generator
```

Example for Linux:

```text
/var/www/html/qp-generator
```

If using Git:

```bash
git clone https://github.com/your-username/question-paper-generator.git
cd question-paper-generator
```

Replace the repository URL with the actual GitHub repository URL.

### 2. Install Dependencies

Run:

```bash
composer install
```

or, if dependencies have not yet been added:

```bash
composer require phpoffice/phpspreadsheet phpoffice/phpword
```

### 3. Check Folder Permissions

The application automatically creates its storage directories when required.

The web server/PHP process must be able to write to the project directory.

### 4. Start Apache

For XAMPP:

1. Start **Apache**.
2. Open the application in your browser.

Example:

```text
http://localhost/qp-generator/index.php
```

### 5. First Login

On the first run, the application creates an administrator account automatically.

```text
Username: admin
Password: admin123
```

**Change the default password immediately after the first login.**

If the initial administrator account becomes unusable, the application's initialization logic can recreate the default user database after the `data` directory is removed, provided the directory is writable.

---

## 🔒 Security

The application includes several server-side protections.

### Password Security

Passwords are stored using PHP's native:

```php
password_hash()
password_verify()
```

with `PASSWORD_DEFAULT`.

Passwords are not stored as plain text in `users.json`.

### CSRF Protection

State-changing POST requests use a session CSRF token and verify it with `hash_equals()`.

### Session Protection

Successful login regenerates the PHP session ID.

Account/workspace switching also clears previously selected paper and basket state to reduce the risk of cross-user data carry-over.

### Workspace Isolation

Teachers are restricted server-side to their own Question Bank and saved-paper directories.

Administrators can explicitly select a teacher workspace, but the selected owner is resolved and validated on the server.

### Path Traversal Protection

File operations validate paths against the active user's permitted storage directory before reading, loading, deleting, or updating files.

### Direct File Access Protection

The application automatically places `.htaccess` protection in:

```text
/data
/question_banks
/saved_papers
```

using Apache's `Require all denied` directive.

This prevents raw user databases, Question Banks, and saved JSON Question Papers from being directly downloaded through normal browser URLs.

### Question HTML Sanitization

Question text is sanitized before storage/rendering.

Unsafe event-handler attributes and JavaScript URLs are removed, and externally hosted image URLs are not retained by the question sanitizer.

---

## ⚠️ Deployment Notes

For production use:

- Change the default administrator password immediately.
- Use HTTPS.
- Keep PHP and Composer dependencies updated.
- Ensure `data`, `question_banks`, and `saved_papers` are not publicly browsable.
- Keep regular backups of Question Banks and saved Question Papers.
- Do not commit private `users.json`, Question Banks, saved papers, or generated documents to a public GitHub repository.

A suitable `.gitignore` should normally exclude runtime/user data such as:

```gitignore
/data/
/question_banks/
/saved_papers/
/vendor/
```

If `vendor/` is intentionally committed for a particular deployment model, adjust the `.gitignore` policy accordingly.

---

## 🧭 Typical Teacher Workflow

```text
Login
  ↓
Select / create Question Bank
  ↓
Search & filter Available Questions
  ↓
Edit or create questions if required
  ↓
Add questions to Basket
  ↓
Review Blueprint
  ↓
Arrange sections / headings
  ↓
Review Document Preview
  ↓
Save Question Paper
  ↓
Generate Word DOCX / Answer Key
  ↓
Print or distribute
```

---

## 🧩 Technology Stack

| Component | Technology |
|---|---|
| Backend | PHP |
| Frontend | HTML, CSS, JavaScript |
| Authentication | PHP Sessions + JSON user store |
| Question Bank | Microsoft Excel `.xlsx` |
| Excel Library | PhpSpreadsheet |
| Word Export | PhpWord |
| Rich Text Editing | CKEditor |
| Saved Question Papers | JSON |
| Web Server | Apache |
| Deployment | XAMPP / WAMP / LAMP / Apache PHP |

---

## 📌 Current Application Version

The current PHP application defines:

```text
APP_VERSION = v2.7.6
```

---

## 📄 License

Copyright © St. Paul's English School, NSD Compound, Savalanga Road, Shivamogga.

All rights reserved.
