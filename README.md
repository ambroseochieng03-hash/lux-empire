
# LUX EMPIRE

**LUX EMPIRE** is a custom PHP web application that combines **rental housing** and **moving logistics** into one platform.

The platform provides dedicated workflows for **tenants, landlords, drivers, and administrators**, covering property discovery, bookings, moving requests, communication, geolocation, driver tracking, notifications, emergency handling, and administrative monitoring.

The project is intentionally implemented as a **custom PHP application** rather than relying on a large PHP framework.

> **Project Status:** Active development / portfolio project. Core application workflows are implemented, while production hardening, testing, infrastructure, and operational configuration remain ongoing.

---

## Core Features

### Authentication & Account Management

- User registration and login
- Role-based access control
- Tenant, landlord, driver, and administrator roles
- Argon2id password hashing
- Password hash rehashing
- Password reset through email tokens
- Account activation and suspension
- CSRF protection
- Centralized session management
- Session fixation mitigation
- Session ID regeneration after authentication
- Idle session timeout
- Absolute session lifetime
- Periodic session ID rotation
- Secure session cookies
- `HttpOnly` cookies
- `SameSite=Lax`
- HTTPS-aware `Secure` cookies

---

## Tenant Features

Tenants can:

- Search available properties
- Browse property listings
- View property details
- Book properties
- View and manage bookings
- Request moving services
- Provide pickup and destination locations
- Specify moving dates
- Describe their load
- View pricing information
- Track assigned drivers
- Share location during relevant workflows
- Communicate with landlords
- Communicate with drivers
- Receive notifications
- Trigger emergency alerts

---

## Landlord Features

Landlords can:

- Add properties
- Edit property information
- Manage property listings
- Upload property images
- Upload property videos
- Manage property media
- View incoming booking requests
- Approve or reject booking requests
- Communicate with tenants
- Receive notifications

---

## Driver Features

Drivers can:

- View available moving requests
- Accept transport requests
- Manage active trips
- Update trip status
- Share live location
- View trip-related information
- Communicate with tenants
- Receive notifications
- Participate in emergency and trip workflows

---

## Administrator Features

Administrators have a dedicated management interface for:

- User management
- Account activation
- Account suspension
- User deletion
- Property management
- Truck and moving request monitoring
- Reports
- Emergency alerts
- Emergency status management
- Platform activity monitoring

---

# Messaging System

LUX EMPIRE contains a custom database-backed messaging system.

The messaging subsystem supports:

- Conversation creation
- Conversation retrieval
- Participant authorization
- Message sending
- Message retrieval
- Incremental message polling
- Unread message counts
- Read-state tracking
- Typing indicators
- Online presence
- Last-seen information
- House-related conversations
- Truck-request-related conversations

The messaging system is implemented using application classes and database tables rather than relying on an external chat platform.

---

## Optional AI Integration

The application contains an optional Groq API integration through:

```text
classes/GroqClient.php

The current AI functionality is deliberately narrow.

When a participant has been waiting without receiving a response for a configured period, the system can generate a contextual notification indicating that the other participant has been notified.

The AI component is designed **not to impersonate users or invent information about availability, location, or timing**.

Configuration:

```env
GROQ_API_KEY=
GROQ_MODEL=llama-3.3-70b-versatile
```

The AI integration is optional and does not prevent the core messaging system from operating.

---

# Property Media Pipeline

Property media is handled centrally through:

```text
classes/MediaService.php
```

## Images

Supported image formats include:

* JPEG
* PNG
* WebP

The image pipeline includes:

* MIME detection using the actual file contents
* Image metadata validation
* Application-level upload limits
* Image resizing
* WebP conversion
* Generated filenames
* Safe filename/path handling

Large images are resized to a maximum configured width before storage.

---

## Videos

Video uploads are processed through FFmpeg.

The video pipeline includes:

* MIME validation
* Application-level upload limits
* FFmpeg normalization
* MP4 output
* H.264 video
* AAC audio
* `faststart` metadata

Example processing dependency:

```bash
ffmpeg
```

Image processing requires PHP GD:

```bash
php-gd
```

---

# Maps & Location Tracking

LUX EMPIRE integrates geographic data into property and transport workflows.

Implemented capabilities include:

* Property coordinates
* Truck pickup coordinates
* Truck destination coordinates
* Driver live-location updates
* Tenant location updates
* Driver location retrieval
* Route-data retrieval
* Trip location history
* Tenant-facing driver tracking

Google Maps configuration is supplied through environment variables rather than being hard-coded into the application.

```env
GOOGLE_MAPS_API_KEY=
```

---

# Notifications

The application includes a database-backed notification system.

Supported operations include:

* Notification creation
* Notification retrieval
* Read/unread state
* Notification deletion
* Links back to application workflows

Notifications are used throughout booking, transport, messaging, and administrative workflows.

---

# Emergency System

LUX EMPIRE contains an emergency-alert subsystem designed around transport and user safety workflows.

Emergency records can contain:

* User information
* User role
* Location information
* Booking references
* Truck-request references
* Emergency status
* Activity history

Administrators can monitor emergency events and update their status.

The application also maintains emergency activity logs.

---

# Trip Tracking & History

Transport activity is persisted through dedicated tracking tables and application logic.

The system records information such as:

* Driver locations
* Tenant locations
* Trip locations
* Trip status changes
* Transport request state
* Emergency activity

This provides a historical record rather than relying exclusively on the driver's current position.

---

# 🗄️ Database

LUX EMPIRE uses **MariaDB/MySQL** with PDO.

Major database entities include:

```text
users
houses
house_images
bookings
drivers
truck_requests
driver_locations
tenant_locations
password_resets
activity_logs
reports
emergency_alerts
emergency_activity_logs
trip_location_logs
trip_status_history
conversations
messages
chat_typing
notifications
```

Relationships between users, properties, bookings, transport requests, conversations, messages, and tracking records are maintained through relational database constraints.

Application database access uses prepared PDO statements.

Example:

```php
$stmt = $pdo->prepare(
    'SELECT * FROM users WHERE email = :email LIMIT 1'
);

$stmt->execute([
    'email' => $email
]);
```

---

# Security Architecture

Security is implemented across multiple layers.

## Application Layer

* Prepared PDO statements
* Argon2id password hashing
* Password hash rehashing
* Authentication checks
* Role-based access flows
* Authorization checks
* CSRF protection
* Session fixation mitigation
* Participant authorization for conversations

---

## Session Layer

The centralized session configuration provides:

* Secure cookie configuration
* `HttpOnly`
* `SameSite=Lax`
* HTTPS-aware `Secure`
* Strict session mode
* Cookie-only sessions
* Disabled transparent session IDs
* Idle timeout
* Absolute session lifetime
* Periodic session-ID rotation
* Session regeneration after authentication
* Complete session destruction on logout/expiry

---

## Upload Security

Uploaded media is not trusted based solely on the browser-provided filename or extension.

The application performs MIME detection against the actual uploaded file and processes media before storage.

Images are processed through PHP GD, while videos are normalized through FFmpeg.

---

## Apache Security

The Apache configuration provides clean application routes and restricts direct access to sensitive application directories.

Sensitive areas such as:

```text
config/
classes/
includes/
```

are protected from direct public access.

Sensitive file extensions are also restricted, including:

```text
.env
.log
.sql
.md
```

> Security configuration should still be comprehensively tested before production deployment. Application-level controls do not replace secure filesystem permissions, HTTPS, database privileges, API-key restrictions, monitoring, and infrastructure hardening.

---

# Application Architecture

LUX EMPIRE follows a lightweight custom PHP architecture.

```text
lux-empire/
│
├── api/
│   ├── admin/
│   ├── auth/
│   ├── bookings/
│   ├── chat/
│   ├── emergency/
│   ├── houses/
│   ├── maps/
│   ├── notifications/
│   └── trucks/
│
├── assets/
│
├── auth/
│
├── classes/
│
├── config/
│
├── dashboard/
│   ├── admin/
│   ├── driver/
│   ├── landlord/
│   └── tenant/
│
├── database/
│
├── includes/
│
├── logs/
│
├── vendor/
│
├── .env.example
├── .htaccess
├── composer.json
└── README.md
```

---

# Main Application Classes

The `classes/` directory contains the primary application and service logic.

| Class               | Responsibility                  |
| ------------------- | ------------------------------- |
| `Auth.php`          | Registration and authentication |
| `User.php`          | User operations                 |
| `House.php`         | Property management             |
| `Booking.php`       | Booking workflows               |
| `TruckRequest.php`  | Moving/transport workflows      |
| `DriverTracker.php` | Driver tracking                 |
| `Chat.php`          | Messaging, presence and typing  |
| `GroqClient.php`    | Groq API integration            |
| `Mailer.php`        | SMTP email delivery             |
| `MediaService.php`  | Image/video processing          |
| `Notification.php`  | Application notifications       |

---

# URL Routing

Apache rewrite rules provide clean application URLs.

Examples:

```text
/luxempire/login
/luxempire/register

/luxempire/tenant
/luxempire/landlord
/luxempire/driver
/luxempire/admin

/luxempire/manage-houses
/luxempire/add-property
/luxempire/booking-requests

/luxempire/tenant/search-houses
/luxempire/tenant/my-bookings
/luxempire/tenant/request-truck
/luxempire/tenant/track-driver

/luxempire/driver/available-requests
/luxempire/driver/active-trip
/luxempire/driver/location-tracker

/luxempire/admin/users
/luxempire/admin/houses
/luxempire/admin/truck-requests
/luxempire/admin/reports
/luxempire/admin/emergency
```

> The physical PHP implementation remains organized beneath the application directories while Apache exposes the intended clean routes.

---

# Technology Stack

## Backend

* PHP 8.x
* Object-oriented PHP
* PDO
* MariaDB / MySQL
* Composer

## Frontend

* HTML5
* CSS3
* JavaScript
* Browser Geolocation APIs
* Asynchronous HTTP requests

## Web Server

* Apache
* `.htaccess`
* Apache URL rewriting

## Libraries & Services

* PHPMailer
* `vlucas/phpdotenv`
* Google Maps JavaScript API
* Groq API
* PHP GD
* FFmpeg

---

# Requirements

A local installation requires:

```text
PHP 8.x
PDO
PHP GD
MariaDB or MySQL
Apache
Composer
FFmpeg
```

Additional services may be required depending on enabled functionality:

```text
SMTP credentials
Google Maps API key
Groq API key (optional)
```

---

# Installation

Clone the repository:

```bash
git clone https://github.com/ambroseochieng03-hash/lux-empire.git
```

Enter the project:

```bash
cd lux-empire
```

Install Composer dependencies:

```bash
composer install
```

Create the environment file:

```bash
cp .env.example .env
```

Configure the environment variables:

```bash
nano .env
```

Import the database schema:

```text
database/schema.sql
```

Configure Apache so that:

* URL rewriting is enabled
* `.htaccess` is active
* the application is served from its intended path
* sensitive directories cannot be accessed directly

---

# Environment Configuration

LUX EMPIRE uses `vlucas/phpdotenv` for environment configuration.

Example:

```env
DB_HOST=localhost
DB_NAME=house_truck_platform
DB_USER=root
DB_PASS=

GOOGLE_MAPS_API_KEY=

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_EMAIL=
MAIL_FROM_NAME="Lux Empire"

GROQ_API_KEY=
GROQ_MODEL=llama-3.3-70b-versatile
```

Never commit real secrets.

Do not place the following in Git:

```text
Database passwords
SMTP passwords
API keys
Production credentials
Private user information
.env
```

---

# Email

Email functionality is implemented through **PHPMailer** and SMTP.

The mail configuration supports:

```text
SMTP host
SMTP port
SMTP username
SMTP password
SMTP encryption
Sender email
Sender name
```

Password-reset functionality uses the application's mail service to deliver reset messages.

---

# Google Maps Setup

To enable map functionality:

1. Create a Google Cloud project.
2. Create an appropriate Maps API key.
3. Enable the APIs required by the application's map workflows.
4. Add the key to `.env`.

```env
GOOGLE_MAPS_API_KEY=your_key_here
```

The production API key should be appropriately restricted.

---

# Media Processing Setup

Image processing requires PHP GD.

On a Linux environment, verify GD with:

```bash
php -m | grep gd
```

Verify FFmpeg with:

```bash
ffmpeg -version
```

The PHP process must be able to execute FFmpeg for video-processing functionality.

Because property videos can be large, deployment should account for:

* PHP upload limits
* Apache request limits
* Available storage
* FFmpeg processing time
* Request timeouts
* Backup policies
* Media retention

---

# Development

LUX EMPIRE is intentionally built without Laravel or another large PHP framework.

The project demonstrates practical implementation of:

* Object-oriented PHP
* Composer dependency management
* PDO
* Relational database design
* Authentication
* Authorization
* Session security
* CSRF protection
* HTTP/API endpoints
* File upload validation
* Image processing
* Video processing
* Geolocation
* Mapping
* Driver tracking
* Database-backed messaging
* Presence and typing indicators
* SMTP email
* AI API integration
* Role-specific dashboards
* Apache rewrite routing

---

# Production Readiness

The implementation of core application workflows does **not** automatically mean that the system is production-ready.

Before a public launch, the following should be addressed and verified:

* Comprehensive security testing
* Authorization testing for every sensitive endpoint
* CSRF coverage testing
* Rate limiting
* Brute-force protection
* Least-privilege database accounts
* HTTPS-only deployment
* Secure PHP configuration
* Secure Apache configuration
* Upload/resource limits
* Background processing for expensive FFmpeg jobs
* Production media storage
* Database backups
* Disaster recovery
* Monitoring
* Error management
* Audit logging
* API-key restrictions
* Secret rotation
* Privacy/data-protection review
* Database migration/versioning
* Automated tests
* Load/concurrency testing
* Deployment automation
* Rollback procedures

These are production engineering requirements and should not be interpreted as a claim that the existing application contains no implementation issues.

---

# Future Improvements

Potential future development areas include:

* Online payments
* Transaction reconciliation
* Push notifications
* Advanced analytics
* Background job processing
* Object storage for property media
* More granular permissions
* Automated testing
* CI/CD
* Dockerized deployment
* Mobile applications
* Stronger real-time tracking infrastructure
* Improved observability
* Expanded AI-assisted support

---

# 👨‍💻 Author

**Ambrose Ochieng**

GitHub:

[https://github.com/ambroseochieng03-hash](https://github.com/ambroseochieng03-hash)

---

## Repository

[https://github.com/ambroseochieng03-hash/lux-empire](https://github.com/ambroseochieng03-hash/lux-empire)

`````markdown
composer install
`````

`````
<?php

$pdo = new PDO(...);
`````


````markdown
SELECT *
FROM users
WHERE email = :email;
````


````markdown
DB_HOST=localhost
DB_NAME=house_truck_platform
````

