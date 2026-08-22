# Bitrix24 Custom Task Chat Widget with Message Visibility Control

A custom embedded Bitrix24 Chat Widget for the Task section built with **PHP**, **HTML5**, **CSS3**, and **Vanilla JavaScript**. It allows users to set message visibility scopes before sending messages inside a task card.

---

## 🌟 Key Features
- **Bitrix24 Native Placement**: Embeds directly into Bitrix24 Task cards (`TASK_VIEW_TAB` and `TASK_VIEW_SIDEBAR`).
- **Message Visibility Controls**:
  - 🌐 **Public**: Visible to all task participants.
  - 🔒 **Internal Team**: Visible only to internal team members (Creator, Assignee, Accomplices, Auditors), hiding from external client guests.
  - 👥 **Creator & Assignee Only**: Restricted to only the Task Creator and Task Responsible.
- **Role-based Backend Filtering**: Access rules are validated on the backend via Bitrix24 `tasks.task.get` and `user.current` APIs.
- **Zero-Config Database**: Uses SQLite via PHP PDO for seamless setup without needing external MySQL setup (MySQL can be enabled easily if required).
- **Modern Responsive Design**: Glassmorphic UI, status badges, dark/light theme awareness, smooth animations, auto-resizing iframe (`BX24.resizeWindow`).

---

## 📁 File Structure
```
task_chat/
├── config.php            # App configuration (Client ID, Secret, Webhook URL)
├── crestclass.php        # Bitrix24 REST API Client Class
├── crest.php             # REST API Wrapper Loader
├── db.php                # SQLite PDO Connection & Migration
├── install.php           # Bitrix24 App Placement Installer
├── index.php             # Chat Widget Entrypoint (Placement Handler)
├── api.php               # AJAX API Handler (get_messages, send_message)
├── assets/
│   ├── css/
│   │   └── widget.css    # Premium CSS design system
│   └── js/
│       └── widget.js     # Bitrix24 JS SDK & Chat logic
└── README.md             # Documentation
```

---

## 🚀 How to Run & Install

### 1. Host the Application
Make sure you have PHP installed with PDO SQLite support. Start a local PHP dev server:
```bash
php -S localhost:8000
```

*Note: For Bitrix24 cloud portals, your app must be accessible over HTTPS (e.g. using ngrok, localtunnel, or a live web server).*

### 2. Register as a Local Application in Bitrix24
1. Open your Bitrix24 account.
2. Go to **Developer Resources** -> **Integrations** -> **Add Local Application**.
3. Select **Add Application**.
4. Set required scopes:
   - `task` (Tasks)
   - `placement` (Widget Placements)
   - `user` (Users)
5. Set **Install URL**: `https://your-domain.com/install.php`
6. Set **Handler URL**: `https://your-domain.com/index.php`
7. Click **Save**.

### 3. Verify in Bitrix24 Task Card
1. Open any task in Bitrix24.
2. Under the **Applications** block (or tabs), click **Task Secure Chat**.
3. Select a visibility scope (🌐 Public, 🔒 Internal, 👥 Creator & Assignee), type your message, and hit **Send**.

---

## 🔒 Security & Privacy
Restricted messages are filtered out **at the PHP server layer**. Unprivileged users never receive hidden message text over HTTP requests.
# task_chat
