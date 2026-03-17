# 🎮 Gamified Platform — PHP + MySQL Backend

PHP REST API for the Gamified College Learning & Placement Platform.
No frameworks — pure PHP with PDO. Works on XAMPP, LAMP, or any shared hosting.

---

## ⚡ Quick Start (XAMPP)

### 1. Setup
```bash
# Copy project into XAMPP htdocs
cp -r server/ C:/xampp/htdocs/gamified-api/

# OR on Linux/Mac
cp -r server/ /var/www/html/gamified-api/
```

### 2. Configure environment
```bash
# Edit config/env.example.php with your DB credentials
# Rename it to env.php (and update the require in database.php)
```

### 3. Create the database
```sql
-- Open phpMyAdmin or MySQL CLI
mysql -u root -p < utils/schema.sql
```

### 4. Seed the database
```bash
php utils/seed.php
```

### 5. Test it
```
GET http://localhost/gamified-api/api/health
```
You should see: `{"success":true,"message":"Gamified Platform PHP API is running 🚀"}`

---

## 📡 API Endpoints

All endpoints are identical to the Node.js version — your React frontend works unchanged.

### Auth
| Method | Endpoint | Auth |
|--------|----------|------|
| POST | `/api/auth/register` | ❌ |
| POST | `/api/auth/login` | ❌ |
| GET  | `/api/auth/me` | ✅ JWT |

### Progress
| Method | Endpoint | Auth |
|--------|----------|------|
| GET  | `/api/progress/dashboard` | ✅ |
| GET  | `/api/progress/level/:n/tasks` | ✅ |
| POST | `/api/progress/complete-task/:taskId` | ✅ |

### Users
| Method | Endpoint | Auth |
|--------|----------|------|
| GET   | `/api/users/peers` | ✅ |
| GET   | `/api/users/:id` | ✅ |
| PATCH | `/api/users/profile` | ✅ |
| POST  | `/api/users/change-password` | ✅ |

### Leaderboard
| Method | Endpoint | Auth |
|--------|----------|------|
| GET | `/api/leaderboard` | ✅ |

### Community
| Method | Endpoint | Auth |
|--------|----------|------|
| POST  | `/api/community/request/:mentorId` | ✅ |
| PATCH | `/api/community/request/:connectionId` | ✅ |
| GET   | `/api/community/my-connections` | ✅ |

### Admin
| Method | Endpoint | Auth |
|--------|----------|------|
| GET   | `/api/admin/stats` | ✅ Admin |
| GET   | `/api/admin/users` | ✅ Admin |
| PATCH | `/api/admin/users/:id/toggle` | ✅ Admin |
| POST  | `/api/admin/users/:id/xp` | ✅ Admin |
| POST  | `/api/admin/levels` | ✅ Admin |
| POST  | `/api/admin/tasks` | ✅ Admin |
| PATCH | `/api/admin/tasks/:id` | ✅ Admin |

---

## 🏗️ Project Structure

```
server/
├── index.php                    ← Single entry point (all routes here)
├── .htaccess                    ← Rewrites all requests to index.php
├── config/
│   ├── database.php             ← PDO MySQL singleton connection
│   └── env.example.php          ← Environment config (rename to env.php)
├── middleware/
│   └── AuthMiddleware.php       ← JWT protect() + adminOnly()
├── controllers/
│   ├── AuthController.php       ← register, login, me
│   ├── ProgressController.php   ← completeTask, getDashboard, getLevelTasks
│   ├── UserController.php       ← profile, peers, changePassword
│   └── OtherControllers.php     ← Leaderboard, Community, Admin
├── utils/
│   ├── JwtHelper.php            ← JWT encode/decode (no library needed)
│   ├── Response.php             ← Standardized JSON response helpers
│   ├── BadgeEngine.php          ← Auto badge award logic
│   ├── schema.sql               ← Full MySQL schema (run this first)
│   └── seed.php                 ← Seed script for levels/tasks/badges/admin
```

---

## 🔒 Security
- Passwords hashed with `password_hash()` (bcrypt, cost 12)
- JWT signed with HMAC-SHA256 — no external library
- Prepared statements (PDO) on every query — SQL injection safe
- Input validation on all auth endpoints
- Passwords never returned in any API response

---

## 🔑 Default Admin (after seed)
- **Email:** admin@platform.com
- **Password:** Admin@123456
- ⚠️ Change this immediately!

---

## 🌐 Deploying to Shared Hosting (000webhost, InfinityFree, etc.)
1. Upload all files via FTP
2. Create MySQL database in cPanel
3. Run `schema.sql` via phpMyAdmin
4. Update `config/env.example.php` with your DB credentials
5. Run `seed.php` once via the hosting file manager or SSH
6. Your API is live!
