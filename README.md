# Nia Life OS — Laravel Backend

Full REST API for Nia Life OS. Auth, memory sync, conversations, Paystack subscriptions, WhatsApp reminders.

## Deploy on Railway
1. Connect this repo to Railway
2. Add PostgreSQL service
3. Set environment variables (see .env.example)
4. Railway auto-runs migrations and starts the server

## API Base URL
https://laravel-production-7870.up.railway.app/api

## Key endpoints
- POST /api/auth/register
- POST /api/auth/login  
- GET  /api/memory
- PUT  /api/memory
- GET  /api/conversations
- POST /api/conversations
- POST /api/subscription/initialize
- GET  /api/admin/stats (admin only)
