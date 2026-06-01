# University Course Registration Chatbot

An AI-powered web application that helps students at King Faisal University plan conflict-free course schedules through a conversational chatbot interface. The system combines a graph coloring algorithm for automated conflict resolution with a DeepSeek-backed NLP chatbot, all wrapped in a secure, full-stack web application.

---

## Overview

Course registration is a recurring challenge for students — conflicting time slots, limited seat availability, and the difficulty of building a balanced schedule manually. This project addresses those problems by automating schedule generation and providing a conversational assistant for registration-related questions.

The system was built as a capstone project for the Bachelor of Science in Computer Science at King Faisal University, College of Computer Sciences and Information Technology.

---

## Features

- Conflict-free schedule generation using a DSATUR-style graph coloring algorithm
- AI chatbot powered by the DeepSeek API with a custom system prompt tailored to KFU course data
- Support for queries in both Arabic and English
- File upload support (PDF, Word, Excel, PowerPoint) with automatic text extraction sent to the chatbot
- User authentication with JWT tokens, bcrypt password hashing, and HTTP-only cookies
- Role-based access control separating student and admin views
- Admin dashboard for monitoring user inquiries and chatbot responses
- Dark mode toggle
- Persistent chat history organized by conversation
- Web scraping pipeline to extract course data from the KFU registration portal and Blackboard PDF exports

---

## Tech Stack

**Frontend:** HTML, CSS, JavaScript

**Backend:** Node.js, Express.js, PHP

**Database:** MySQL (via XAMPP)

**LLM:** DeepSeek API

**Scraping:** Python, BeautifulSoup, PDFPlumber

**Data processing:** Pandas, NumPy

**Security:** bcrypt.js, JSON Web Tokens, HTTPS

**File handling:** PHPOffice, PHPWord, PHPSpreadsheet, OCR.space API

---

## How It Works

### Graph Coloring Algorithm

The graph coloring logic is embedded directly in the chatbot's system prompt. When a student requests a schedule, the chatbot applies the conflict resolution logic as part of its response generation — modeling sections as nodes, treating time and course overlaps as edges, and assigning conflict-free time slots using a DSATUR-style coloring approach. There is no separate backend service running the algorithm; the prompt instructs the model on how to reason through conflicts and produce a valid schedule.

### Data Acquisition

Course data is scraped from two sources. The KFU registration portal is scraped using BeautifulSoup, navigating nested HTML table structures and filtering non-data rows. Blackboard course data arrives as a PDF and is processed with PDFPlumber, which handles row spans, multi-line cells, and reversed Arabic text direction.

### Chatbot Integration

The scraped course data is embedded directly into the DeepSeek system prompt at runtime. This gives the model access to up-to-date course, schedule, and instructor information without requiring fine-tuning. Users can also upload documents; the system extracts text from the file and appends it to the query before sending it to the API.

---

## Setup Instructions

### Prerequisites

- XAMPP (Apache + MySQL + PHP)
- Node.js
- Python 3 with the following packages: `beautifulsoup4`, `pdfplumber`, `pandas`, `numpy`, `requests`
- A DeepSeek API key

### Steps

1. Place all project files inside the XAMPP `server` directory.

2. Set up the database by importing `database.sql` through phpMyAdmin or the MySQL CLI.

3. Create a `.env` file in the project root with the following:
   ```
   DEEPSEEK_API_KEY=your_api_key_here
   ```

4. In the XAMPP shell, navigate to the `public` folder and run:
   ```
   php -S 127.0.0.1:8000
   ```

5. In a separate terminal, from the project root run:
   ```
   node server-secure.js
   ```

6. If dependencies are missing, install them with:
   ```
   npm install express axios csv-parser jsonwebtoken bcrypt dotenv
   ```

7. Open your browser and navigate to `http://localhost:8000`.

---

## Database Schema

Three tables are used in the implementation:

- `admin_users` — stores user accounts with hashed passwords and preferences such as dark mode
- `user_inquiries` — logs all chatbot conversations, including queries, responses, and timestamps
- `user_remember_tokens` — manages persistent login sessions with selectors, hashed tokens, and expiry metadata

---

## Testing

Testing was conducted across four areas:

- **Data scraping** — verified correct extraction, text normalization, and removal of redundant rows from both data sources
- **DeepSeek integration** — tested course and instructor queries, out-of-scope questions, and bilingual responses
- **Web application** — covered registration, login, authentication edge cases, file uploads, dark mode, and admin log access
- **Graph coloring** — unit-tested time parsing, overlap detection, conflict edge creation, coloring behavior on known graph structures, and end-to-end schedule generation with zero conflicts reported

---

## Known Limitations

- The web scraper depends on the structure of the KFU portal and Blackboard PDF layout. Changes to either source may require updates to the scraping scripts.
- File text extraction from non-English documents is limited by the OCR.space API, which has language and size restrictions.
- The system is currently configured for local development using XAMPP. A production deployment would require migrating to a cloud-managed database and a proper hosting environment.

---

## Authors

- Hashim Hamad Mohammed Alshawaf
- Mujtaba Mohammed Nour Ismail
- Abdulelah Aluthman

Supervised by Prof. Fawaz Al-Saade and Dr. Khaled Riad.
King Faisal University, College of Computer Sciences and Information Technology, January 2026.
