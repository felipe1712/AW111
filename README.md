# WP Auto Whats

A WordPress plugin to connect your site with the WAHA API for seamless WhatsApp messaging, contact management, and session control from your WordPress dashboard.

---

## Features

- Send and receive WhatsApp messages directly from WordPress
- Manage contacts and chat sessions
- Attach images and documents
- Full chat history download
- Modern, intuitive admin interface

---

## Installation

1. **Upload the Plugin**  
    Copy the `wp-auto-whats` folder to your `wp-content/plugins/` directory.

2. **Activate the Plugin**  
    - Go to your WordPress admin dashboard.
    - Navigate to **Plugins > Installed Plugins**.
    - Find **WP Auto Whats** and click **Activate**.

---

## Setup

### 1. Configure API Settings

- Go to **WP Auto Whats > Settings**.
- Set the following:
  - **URL Type:** Select `https` or `http` as required.
  - **API Link:** Enter your WAHA API domain (e.g., `example.com`).
  - **API Sessions:** Enter your session name (e.g., `default`).
- Click **Save**.

### 2. Session Management

- Use the **Session Management** section to:
  - Test API and session connection
  - Create, start, stop, restart, or delete sessions
  - Retrieve session/account info

---

## Usage

### 1. Chat & Messaging

- Go to **WP Auto Whats** in your admin menu.
- The left sidebar lists your WhatsApp chats.
- Click a chat to open it, or start a new chat by entering a phone number.
- Type your message, attach files with the **+** button, and click **Send**.
- Hover over messages for actions: Info, Reply, Delete, Copy, React, Pin/Unpin, Forward.
- Download chat history or refresh chats/messages as needed.

### 2. Contacts

- Go to **WP Auto Whats > Contact List** to view/manage contacts.
- Import contacts from WhatsApp if the list is empty.

---

## Help & Documentation

- Click the **Help** tab on Settings or Contact List pages for step-by-step guides.
- Visit the [Plugin Documentation](#) for more details.

---

## Troubleshooting

- **API Connection:** Verify API URL, session, and WAHA server status.
- **Session Issues:** Use session management to restart or recreate sessions.
- **Media Uploads:** Ensure server allows uploads and file sizes are within limits.

---

## Developer Notes

- All AJAX/API logic uses secure WordPress endpoints.
- Message actions are logged and updated in the database.
- UI uses Dashicons and modern admin styling.

---