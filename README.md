# Question Paper Generator (v2.5.0)

A lightweight, single-file PHP web application designed for schools and educators to manage Excel (`.xlsx`) question banks, assemble examination papers with real-time chapter blueprints, preview print layouts, and export formatted Microsoft Word (`.docx`) question papers and answer keys.

---

## ✨ Key Features

### 🔐 Multi-User & Role-Based Access
* **Administrator & Teacher Roles**: Built-in authentication backed by a zero-configuration JSON datastore (`/data/users.json`).
* **Workspace Isolation**: Each subject teacher gets private, isolated directories for their question banks and saved question papers.
* **Admin Controls**: Administrators can create, edit, enable/disable teacher accounts, reset passwords, and switch into any teacher's workspace to assist with paper preparation.

### 📊 Smart Excel (`.xlsx`) Question Bank Management
* **Auto-Detection of Columns**: Automatically detects column headers (`Chapter`, `QNo`, `Category`, `Marks`, `Difficulty`, `Question`, `OptionA`–`OptionD`, `Answer`) within the first 5 rows of the `Questions` worksheet.
* **In-App Question Creation**: Add new questions directly to the active `.xlsx` workbook from the web interface.
* **Inline Bank Editing**: Edit question text, MCQ options, category, marks, and difficulty in place—changes are written back to the Excel file and synced with the active basket.

### 🔍 Live Filtering & Search
* **Multi-Faceted Filters**: Filter questions by **Category**, **Marks**, **Chapter**, and **Difficulty**.
* **Debounced Live Search**: Instant AJAX search across question numbers, question text, chapters, categories, and MCQ options without full-page reloads.

### 🧺 Interactive Basket & Blueprint Summary
* **Single & Bulk Selection**: Add or remove questions individually or in bulk, with automatic focus jump to the **Print Preview** section upon adding questions.
* **Live Blueprint Table**: Automatically calculates chapter-wise and section-wise question counts and mark weightages.
* **Collapsible Sections & Inline Auto-Save**: Expand or collapse question sections and edit section sort orders (`#`) or custom section headings inline with automatic background saving.

### 📄 Live Print Preview & Professional `.docx` Export
* **Real-Time Document Preview**: Customize the School Name, Paper Title, Subtitle (Class/Subject/Exam), Watermark text, and Document Font (`Cambria`, `Arial`, `Tunga`, `Nudi 01 e`, `Arial Unicode MS` for English and Kannada support).
* **Word (`.docx`) Generation**: Exports cleanly formatted papers with hanging indentations, automatic 1-row or 2×2 MCQ option tables, section mark summaries, watermarks, and page numbering (`Page X of Y`).
* **One-Click Answer Key**: Generates a matching `.docx` Answer Key from the selected basket questions.
* **Direct Browser Printing**: Built-in `@media print` stylesheet for instant printing or PDF saving directly from the browser preview.

### 💾 Save & Restore Question Papers
* **JSON Snapshots**: Save assembled question papers with custom filenames organized into date-stamped folders.
* **Auto-Relinking**: Loading a saved paper restores all sections, custom headings, and document settings while automatically loading and relinking its source Question Bank.
* **Unsaved Changes Protection**: Prompts to **Save & Continue** or **Discard** before switching question banks or loading another paper when the basket is not empty.

---

## 🛠️ Requirements

* **PHP**: 8.0 or higher (with `zip`, `xml`, `gd`, and `mbstring` extensions enabled)
* **Web Server**: Apache (XAMPP / WAMP / LAMP recommended; uses `.htaccess` for data directory protection)
* **Composer**: For installing PhpSpreadsheet and PhpWord dependencies

---

## 🚀 Installation & Setup

1. **Clone the Repository**
   Place the project inside your web server's document root (e.g., `C:/xampp/htdocs/qp-generator` or `/var/www/html/qp-generator`):
   ```bash
   git clone [https://github.com/your-username/question-paper-generator.git](https://github.com/your-username/question-paper-generator.git)
   cd question-paper-generator
   ```

2. **Install PHP Dependencies**
   Run Composer to install `phpoffice/phpspreadsheet` and `phpoffice/phpword`:
   ```bash
   composer require phpoffice/phpspreadsheet phpoffice/phpword
   ```

3. **Verify Directory Permissions**
   The application automatically creates the following directories on first run and protects them with `.htaccess` (`Require all denied`):
   * `/data`
   * `/question_banks`
   * `/saved_papers`
   Ensure your web server has write permissions for the project root.

4. **Log In**
   Open `http://localhost/qp-generator/index.php` in your browser.
   * **Default Username**: `admin`
   * **Default Password**: `admin123`
   > **Important**: Use the **Change Password** button in the top navigation bar immediately after your first login.

---

## 📋 Excel Question Bank Format

Upload `.xlsx` files containing a worksheet named **`Questions`** (or any active sheet). The importer scans the first 5 rows and automatically maps the following column names (case- and symbol-insensitive):

| Field | Supported Column Headers | Required |
| :--- | :--- | :---: |
| **Chapter** | `Chapter`, `ChapterName`, `ChapterID` | Yes |
| **Question No.** | `QNo`, `QuestionNo` | Yes |
| **Category / Type** | `Category`, `QuestionType`, `Type` | Yes |
| **Marks** | `Marks`, `Mark`, `Weightage` | Yes |
| **Question Text** | `Question`, `QuestionText`, `QText` | Yes |
| **Difficulty** | `Difficulty`, `DifficultyLevel` | Optional |
| **Options A–D** | `OptionA`, `OptionB`, `OptionC`, `OptionD` | Optional (MCQs) |
| **Answer** | `Answer`, `CorrectOption`, `AnswerText` | Optional (Answer Key) |

> **Fallback Mapping**: If no matching headers are detected in the first 5 rows, the importer falls back to columns `F` through `P` (`F`=Chapter, `G`=QNo, `H`=Category, `I`=Marks, `J`=Difficulty, `K`=Question, `L`–`O`=Options A–D, `P`=Answer).

---

## 📁 Directory Structure

```text
.
├── index.php               # Main single-file application (Auth, UI, XLSX & DOCX logic)
├── composer.json           # Composer configuration
├── vendor/                 # Composer dependencies (PhpSpreadsheet & PhpWord)
├── data/                   # Auto-created; stores users.json (protected via .htaccess)
├── question_banks/         # Auto-created; per-teacher .xlsx workbooks (protected via .htaccess)
│   ├── admin/
│   └── <teacher_folder>/
└── saved_papers/           # Auto-created; per-teacher saved .json papers by date (protected)
    ├── admin/
    └── <teacher_folder>/
```

---

## 🔒 Security Highlights

* **Password Hashing**: Uses PHP's native `password_hash()` and `password_verify()` (`PASSWORD_DEFAULT`).
* **CSRF Protection**: All state-changing `POST` requests require a valid session CSRF token verified via `hash_equals()`.
* **Path Traversal Prevention**: File loads and deletions validate paths against `realpath()` within the active user's isolated directory.
* **Direct Download Blocking**: Automatically writes `.htaccess` (`Require all denied`) into `/data`, `/question_banks`, and `/saved_papers` so raw Excel, JSON, and user database files cannot be downloaded directly via browser URLs.

---

## 📄 License

Copyright © St. Paul's English School, NSD Compound, Savalanga Road, Shivamogga. All rights reserved.
