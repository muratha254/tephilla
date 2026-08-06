# How LAN Deployment Works

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENT COMPUTERS                         │
│  (Windows, Mac, Linux, Tablets, Phones)                     │
│                                                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                 │
│  │ Browser  │  │ Browser  │  │ Browser  │                 │
│  │ Chrome   │  │ Firefox  │  │  Edge    │                 │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘                 │
│       │             │             │                         │
│       └─────────────┴─────────────┘                         │
│                    │                                         │
│              HTTP Request                                    │
│         http://192.168.1.100                                │
└────────────────────┼─────────────────────────────────────────┘
                     │
                     │ (Local Network - LAN)
                     │
┌────────────────────▼─────────────────────────────────────────┐
│                  SERVER COMPUTER                             │
│              (Windows + XAMPP)                               │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              Windows Firewall                         │  │
│  │  (Allows port 80/8080 for Apache)                    │  │
│  └────────────────────┬─────────────────────────────────┘  │
│                       │                                      │
│  ┌────────────────────▼─────────────────────────────────┐  │
│  │              Apache Web Server                        │  │
│  │  (XAMPP - Port 80)                                   │  │
│  │                                                       │  │
│  │  VirtualHost Configuration:                          │  │
│  │  - Listens on: *:80 (all network interfaces)        │  │
│  │  - DocumentRoot: /public folder                     │  │
│  │  - mod_rewrite: Enabled                             │  │
│  └────────────────────┬─────────────────────────────────┘  │
│                       │                                      │
│  ┌────────────────────▼─────────────────────────────────┐  │
│  │         public/.htaccess                              │  │
│  │  - URL Rewriting Rules                               │  │
│  │  - Routes all requests to index.php                 │  │
│  └────────────────────┬─────────────────────────────────┘  │
│                       │                                      │
│  ┌────────────────────▼─────────────────────────────────┐  │
│  │         public/index.php                             │  │
│  │  - Laravel Entry Point                               │  │
│  │  - Bootstraps Laravel Application                   │  │
│  └────────────────────┬─────────────────────────────────┘  │
│                       │                                      │
│  ┌────────────────────▼─────────────────────────────────┐  │
│  │         Laravel Application                           │  │
│  │  - Routes (routes/web.php)                          │  │
│  │  - Controllers                                       │  │
│  │  - Views (Blade Templates)                          │  │
│  │  - Models                                            │  │
│  │  - Middleware                                         │  │
│  └────────────────────┬─────────────────────────────────┘  │
│                       │                                      │
│  ┌────────────────────▼─────────────────────────────────┐  │
│  │         MySQL Database                                │  │
│  │  (XAMPP - Port 3306)                                 │  │
│  │  - Host: 127.0.0.1 (localhost only)                 │  │
│  │  - NOT accessible from network                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │         File System                                   │  │
│  │  - storage/ (logs, cache, uploads)                   │  │
│  │  - bootstrap/cache/ (compiled config)                │  │
│  └──────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
```

---

## Request Flow: Step-by-Step

### Example: Client accesses `http://192.168.1.100/dashboard`

#### Step 1: Client Browser Request
```
Client Computer (Browser)
    │
    ├─ User types: http://192.168.1.100/dashboard
    │
    └─ Browser sends HTTP GET request
       ├─ Host: 192.168.1.100
       ├─ Path: /dashboard
       └─ Port: 80 (default)
```

#### Step 2: Network Transmission
```
Local Network (LAN)
    │
    ├─ Request travels through router/switch
    ├─ Routed to server computer (192.168.1.100)
    └─ Arrives at server's network interface
```

#### Step 3: Windows Firewall
```
Server Computer - Windows Firewall
    │
    ├─ Checks firewall rules
    ├─ Finds "Apache HTTP Server" rule
    ├─ Allows TCP port 80
    └─ Request passes through
```

#### Step 4: Apache Receives Request
```
Apache Web Server (XAMPP)
    │
    ├─ Listens on port 80 (all interfaces: 0.0.0.0:80)
    ├─ Receives request for 192.168.1.100/dashboard
    ├─ Checks VirtualHost configuration
    │  └─ Matches ServerName: 192.168.1.100
    ├─ Points to DocumentRoot: /public folder
    └─ Looks for file: /public/dashboard
       └─ File doesn't exist (it's a Laravel route)
```

#### Step 5: .htaccess Processing
```
public/.htaccess
    │
    ├─ mod_rewrite is enabled
    ├─ Checks if /dashboard exists as file/folder
    │  └─ No, it doesn't
    ├─ RewriteRule matches: ^ index.php [L]
    └─ Rewrites request to: /index.php
       └─ Passes /dashboard as PATH_INFO
```

#### Step 6: Laravel Bootstrap
```
public/index.php
    │
    ├─ Loads Composer autoloader
    ├─ Requires bootstrap/app.php
    ├─ Creates Laravel Application instance
    ├─ Loads .env configuration
    │  └─ APP_URL=http://192.168.1.100
    ├─ Creates HTTP Kernel
    └─ Captures HTTP Request
```

#### Step 7: Laravel Routing
```
Laravel Application
    │
    ├─ HTTP Kernel processes request
    ├─ Applies middleware (session, CSRF, auth, etc.)
    ├─ Matches route: GET /dashboard
    │  └─ Found in routes/web.php
    ├─ Calls: DashboardController@index
    └─ Executes controller method
```

#### Step 8: Database Query (if needed)
```
Laravel → MySQL
    │
    ├─ Controller uses Model
    ├─ Model queries database
    ├─ Connection: 127.0.0.1:3306 (localhost)
    ├─ MySQL processes query
    └─ Returns data to Laravel
```

#### Step 9: View Rendering
```
Laravel → Blade Template
    │
    ├─ Controller returns view
    ├─ Blade engine compiles template
    ├─ Renders: resources/views/dashboard.blade.php
    ├─ Includes: layouts/sidebar.blade.php
    └─ Generates HTML output
```

#### Step 10: Response Sent Back
```
Response Path (Reverse)
    │
    ├─ Laravel generates HTTP response
    ├─ HTML content + headers
    ├─ Apache receives response
    ├─ Sends through firewall
    ├─ Travels through network
    └─ Client browser receives HTML
       └─ Displays page to user
```

---

## Key Components Explained

### 1. Apache VirtualHost

**Purpose:** Tells Apache how to handle requests for your application

```apache
<VirtualHost *:80>
    ServerName 192.168.1.100          # Server's IP address
    DocumentRoot "C:/.../public"      # Laravel's public folder
    
    <Directory "C:/.../public">
        AllowOverride All             # Enable .htaccess
        Require all granted           # Allow network access
    </Directory>
</VirtualHost>
```

**What it does:**
- Listens on port 80 for all network interfaces (`*:80`)
- Routes requests to Laravel's `public` folder
- Enables `.htaccess` processing
- Allows access from network (not just localhost)

### 2. .htaccess File

**Location:** `public/.htaccess`

**Purpose:** URL rewriting - converts clean URLs to Laravel routes

**How it works:**
```apache
# If file/folder doesn't exist, send to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

**Example:**
- Request: `http://192.168.1.100/dashboard`
- File `/dashboard` doesn't exist
- Rewrites to: `index.php` with PATH_INFO = `/dashboard`
- Laravel receives: `/dashboard` route

### 3. Laravel Entry Point

**Location:** `public/index.php`

**What it does:**
1. Loads Composer autoloader (all PHP classes)
2. Bootstraps Laravel application
3. Creates HTTP Kernel
4. Captures incoming request
5. Processes through middleware
6. Routes to controller
7. Returns response

### 4. Database Connection

**Important:** Database runs ONLY on server

```env
DB_HOST=127.0.0.1    # localhost only
DB_PORT=3306
```

**Why 127.0.0.1?**
- MySQL only accepts connections from localhost
- Clients CANNOT access database directly
- Only Laravel (on server) can query database
- This is a security feature

### 5. File Permissions

**Why needed:**
- Laravel needs to write logs: `storage/logs/`
- Laravel caches config: `bootstrap/cache/`
- Users upload files: `storage/app/public/`

**Windows Solution:**
- Grant "Everyone" full control on `storage/` and `bootstrap/cache/`
- Or use `icacls` command

---

## Network Communication

### How Clients Find Server

1. **IP Address Resolution:**
   ```
   Client types: http://192.168.1.100
   Browser resolves: 192.168.1.100 → Server's network interface
   ```

2. **Port Communication:**
   ```
   Default HTTP port: 80
   Request: 192.168.1.100:80/dashboard
   Apache listens on: 0.0.0.0:80 (all interfaces)
   ```

3. **Firewall Rules:**
   ```
   Windows Firewall checks:
   - Is port 80 allowed? ✓
   - Is Apache in allowed apps? ✓
   - Request passes through
   ```

### Why It Works on LAN

1. **Same Network:**
   - All devices on same router/switch
   - Can communicate via IP addresses
   - No internet required

2. **Apache Listens on All Interfaces:**
   - `*:80` means "listen on all network interfaces"
   - Not just `127.0.0.1:80` (localhost only)
   - Accepts connections from network

3. **Firewall Allows Inbound:**
   - Windows Firewall rule allows port 80
   - Apache can receive requests from network
   - Not blocked by security

---

## Example Scenarios

### Scenario 1: User Logs In

```
1. Client: http://192.168.1.100/login
2. Apache: Receives request → Routes to Laravel
3. Laravel: Shows login form (Blade template)
4. User: Enters credentials → Submits form
5. Laravel: Validates against database (127.0.0.1)
6. Laravel: Creates session (storage/framework/sessions)
7. Laravel: Redirects to dashboard
8. Client: Receives HTML → Displays dashboard
```

### Scenario 2: Health Check (/ping)

```
1. Client: http://192.168.1.100/ping
2. Apache: Routes to Laravel
3. Laravel: Matches route in routes/web.php
4. Laravel: Returns JSON response
   {
     "status": "OK",
     "message": "Server OK",
     "timestamp": "2024-01-01 12:00:00",
     "app_name": "UTAMADUNI POS System"
   }
5. Client: Receives JSON → Confirms server is accessible
```

### Scenario 3: Static Asset (CSS/Image)

```
1. Client: http://192.168.1.100/css/app.css
2. Apache: Checks /public/css/app.css
3. File exists → Apache serves directly
4. No Laravel processing needed
5. Client: Receives CSS file
```

---

## Security Flow

### What Clients CAN Access:
- ✅ Web pages (HTML)
- ✅ Static assets (CSS, JS, images)
- ✅ API endpoints (if public)
- ✅ Login/logout functionality

### What Clients CANNOT Access:
- ❌ Database directly (MySQL on 127.0.0.1)
- ❌ Laravel source code (outside public/)
- ❌ .env file (not in public/)
- ❌ Storage files (unless explicitly shared)
- ❌ Server file system

### Security Layers:

1. **Apache:** Only serves `public/` folder
2. **Laravel:** Processes all requests through framework
3. **Database:** Only accepts localhost connections
4. **Firewall:** Controls network access
5. **File Permissions:** Restricts file access

---

## Why This Architecture Works

### ✅ No Installation on Clients
- Clients only need a web browser
- All processing happens on server
- No PHP, MySQL, or Laravel needed on clients

### ✅ Centralized Data
- Single database on server
- All data in one place
- Easy to backup and manage

### ✅ Easy Updates
- Update code on server only
- All clients see changes immediately
- No client-side updates needed

### ✅ Network Efficient
- Only HTML/CSS/JS sent to clients
- Database queries stay on server
- Minimal network traffic

---

## Troubleshooting Flow

### If Client Can't Access:

```
1. Check Server IP
   └─ Run: ipconfig (on server)
   └─ Verify IP matches APP_URL

2. Check Apache
   └─ Is Apache running? (XAMPP Control Panel)
   └─ Check error log: C:\xampp\apache\logs\error.log

3. Check Firewall
   └─ Is port 80 allowed?
   └─ Run: netsh advfirewall firewall show rule name="Apache HTTP Server"

4. Check Network
   └─ Can client ping server? ping 192.168.1.100
   └─ Are devices on same network?

5. Check Laravel
   └─ View logs: storage/logs/laravel.log
   └─ Verify .env configuration
```

---

## Summary

**The system works because:**

1. **Apache** acts as the web server, receiving HTTP requests from the network
2. **VirtualHost** routes requests to Laravel's `public` folder
3. **.htaccess** rewrites URLs so Laravel can handle them
4. **Laravel** processes requests, queries database, renders views
5. **MySQL** stores data (only accessible from server)
6. **Response** flows back through the same path to the client

**Clients only need:**
- Web browser
- Network connection
- Server's IP address

**Everything else runs on the server!**



