# Kolko-i-krzyzyk-2 (Tic-tac-toe 2)

This is a WebSocket-based game server built with **Ratchet** in PHP. It supports a customizable multiplayer board game  with real-time updates and admin controls.

---

## 🚀 Features

- Real-time multiplayer gameplay via WebSockets
- Admin-controlled game settings
- Automatic pause/resume
- Detects win or tie
- Graceful shutdown when no players are connected
- Auto-restart logic possible (manual or client-driven)

---

## 🛠 Requirements

- PHP 8.0+
- Composer
- WebSocket-compatible browser/client

---

## 📦 Installation

1. Clone the repository
2. Install dependencies using Composer (run composer install in server directory)

---


## ▶️ Running the Game Server

To start a game server, run the following command from the project root:

```bash
php server.php
