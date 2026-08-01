# LUX EMPIRE

**LUX EMPIRE** is a PHP and MariaDB web application that combines rental housing management with moving logistics in a single platform. It provides separate dashboards for tenants, landlords, drivers, and administrators, allowing each user role to access features relevant to them.

The project was built as a practical full-stack application for managing rental housing and moving logistics while exploring authentication, role-based access control, database design, Google Maps integration, and scalable PHP application architecture.

> **Project Status:** Production-ready. The application is complete and ready for deployment. It is currently maintained as a personal portfolio project.

---

## Features

### Authentication

* User registration and login
* Secure password hashing (Argon2id)
* Password reset via email
* Session management
* Role-based authentication
* CSRF protection
* Prepared statements using PDO

### Tenant

* Search available houses
* View house details
* Book houses
* Request moving trucks
* Track assigned drivers

### Landlord

* Add new houses
* Edit property listings
* Manage bookings
* View booking requests

### Driver

* View available transport requests
* Accept trips
* Share live location
* Update trip status

### Administrator

* User management
* Suspend or activate accounts
* Manage emergency reports
* Monitor platform activity
* Manage houses and transport requests

### Maps

* Google Maps integration
* Live driver tracking
* Route visualization
* Location updates

---

# Technology Stack

## Backend

* PHP
* MariaDB
* PDO
* Composer

## Frontend

* HTML5
* CSS3
* JavaScript

## External Services

* Google Maps JavaScript API
* Gmail SMTP
* PHPMailer

---

# Project Structure

```text
api/            API endpoints
assets/         CSS, JavaScript, images and uploads
auth/           Authentication pages
classes/        Application classes
config/         Application configuration
dashboard/      Role-based dashboards
database/       SQL schema and seed files
includes/       Shared layout components
logs/           Application logs
vendor/         Composer packages
```

---

# Requirements

* PHP 8.x
* MariaDB or MySQL
* Composer
* Apache (or another PHP-supported web server)

---

# Installation

Clone the repository:

```bash
git clone https://github.com/ambroseochieng03-hash/lux-empire.git
```

Move into the project:

```bash
cd lux-empire
```

Install Composer dependencies:

```bash
composer install
```

Import the database:

```text
database/schema.sql
```

(Optional)

```text
database/seeds.sql
```

Create your environment file:

```bash
cp .env.example .env
```

Open `.env` and configure your own values.

---

# Environment Variables

This project stores sensitive information in a `.env` file.

The actual `.env` file is intentionally excluded from Git using `.gitignore`.

Use the provided `.env.example` as a template.

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
```

---

# Composer Dependencies

Install project dependencies using:

```bash
composer install
```

Current packages include:

* PHPMailer
* vlucas/phpdotenv

If additional packages are added in future, Composer will install them automatically from `composer.json`.

---

# Google Maps

To enable maps:

1. Create a Google Maps API key.
2. Enable the required Maps APIs in your Google Cloud project.
3. Add the key to your `.env` file.

---

# Email Configuration

Password reset emails use Gmail SMTP through PHPMailer.

Configure the following values in `.env`:

* MAIL_HOST
* MAIL_PORT
* MAIL_USERNAME
* MAIL_PASSWORD
* MAIL_FROM_EMAIL
* MAIL_FROM_NAME

---

# Security

Sensitive information such as:

* Database credentials
* SMTP credentials
* Google Maps API keys

should never be committed to Git.

Configure them only inside `.env`.

---

# Database

Database scripts are located in:

```text
database/
```

* `schema.sql`
* `seeds.sql`

---

# Screenshots

Screenshots can be added here later.

---

# Future Improvements

Some ideas for future versions include:

* Push notifications
* Online payments
* Deployment with Docker
* Mobile application
* Improved analytics and reporting

---

# License

This project is provided for learning, portfolio, and demonstration purposes.

---

# Author

**Ambrose Ochieng**

GitHub:
https://github.com/ambroseochieng03-hash

---

If you encounter any issues while setting up the project, create an issue in the repository or review the project configuration and environment variables before running the application.
