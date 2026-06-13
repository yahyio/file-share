# Driftbox — Self-Destructing File Share

Upload files and share them with a one-time link. Files auto-expire after 1 hour, 1 day, or 7 days.

[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy)

## Features

- Files stored under random 128-bit tokens
- Expiry options: 1h / 1d / 7d
- Download counter
- Path traversal protection
- Uploads directory blocked from direct access

## Tech Stack

PHP 8.2 · SQLite · Apache

## Run Locally

```bash
php -S localhost:8002
# open http://localhost:8002
```
