# SoCize - File Storage Server

**Final Year Project at [Asia Pacific University](https://apspace.apu.edu.my/)**

SoCize is a secure file-handling module for social media-like environments, built to address growing concerns over data privacy. It allows users to **encrypt files locally** before uploading them to a server. The key idea is that **the server never has access to the unencrypted contents**—encryption and decryption happen entirely on the client side.

This repository includes the **client-side application** for encryption, decryption, and interaction with a backend server.  
> 🔗 The Java-Based Frontend is located here: [SoCize-Client GitHub Repo](https://github.com/YT-07/SoCize-Client.git)

---

## 📑 Table of Contents

- [Introduction](#introduction)
    - [Features](#features)
- [Installation Guide](#installation-guide)
    - [Prerequisites](#prerequisites)
    - [Steps to Run the Application](#steps-to-run-the-application)
- [Server-Side File Overview](#server-side-file-overview)
- [Contributors](#contributors)

---

## 🧩 Introduction

This project simulates how a social media platform can offer secure file sharing without exposing the content to the server.  
All encryption and decryption is done locally by the user.

The **PHP server acts only as a file storage manager**, providing basic services like file upload/download, user login, account management, and access control.

### 🚀 Features

#### Client-Side (This Repo)

- **Encrypt File**: Locally encrypt files using AES or similar before uploading.
- **Decrypt File**: Locally decrypt previously encrypted files using the correct key.
- **Interact with Server**: Upload/download/delete files and authenticate users.

#### Server-Side (PHP Backend)

- Store/retrieve encrypted files.
- Track file ownership.
- Allow **users** to:
  - Upload/download/delete their own files.
  - View their uploaded file list.
- Allow **admins** to:
  - View user accounts.
  - Delete user data (including files and credentials).
  - Monitor server health.

---

## ⚙️ Installation Guide

### Prerequisites

- PHP 8.x
- Apache/Nginx
- MySQL/MariaDB

### Steps to Run the Application

1. Clone both this client-side repo and the [SoCize-Server](https://github.com/Kur3nai/SoCize-Server) PHP backend.
2. Set up the PHP files on your local server (e.g., XAMPP or MAMP).
3. Make sure the backend is running and accessible via browser (e.g., `http://localhost/FileUpload.php`).
4. Run the client-side UI from this repository and test upload/encryption/decryption flows.

---

## 📁 Server-Side File Overview

The screenshot below shows the contents of the PHP server backend:

| Filename              | Description |
|-----------------------|-------------|
| `AccountDetails.php`  | Retrieves or updates current user account details. |
| `AdminViewUser.php`   | Allows the admin to view all user accounts. |
| `DeleteFileData.php`  | Removes a file record and associated metadata from the database. |
| `DeleteUser.php`      | Deletes a user account and all associated files (admin-only). |
| `FileDownload.php`    | Allows users to download their previously uploaded (encrypted) files. |
| `FileUpload.php`      | Receives uploaded files from users; files are already encrypted by the client. |
| `GetFileRecords.php`  | Fetches a list of files uploaded by the authenticated user. |
| `LogIn.php`           | Handles user login and session creation. |
| `LogOut.php`          | Logs the user out by ending the session. |
| `ServerHealth.php`    | Simple ping to check server status. |
| `SignUp.php`          | Registers a new user account. |

> 🔐 **Note**: This server does not perform any file encryption or decryption. It only stores what the client sends and provides access control based on user roles (admin/user).

---

## 👥 Contributors

- **Lead PHP Developer**: [Adam Zikri] [Kur3nai](https://github.com/Kur3nai)
- **SQL Developer**: [Ammar Razeeq Fouad] [Razeeku](https://github.com/Razeeku)
- **Institution**: Asia Pacific University of Technology & Innovation (APU)

---

