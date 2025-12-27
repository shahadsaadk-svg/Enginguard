EnginGuard – Phishing Awareness & Simulation Platform

EnginGuard is a web-based phishing awareness and simulation platform developed as part of an academic project at Bahrain Polytechnic.
The system is designed to help organizations simulate phishing attacks, monitor employee behavior, and improve cybersecurity awareness through real-time feedback, training pages, and detailed analytics.

EnginGuard focuses on the human factor of cybersecurity, transforming risky actions into learning opportunities while providing administrators with clear visibility into employee behavior.

Project Overview

EnginGuard allows administrators to:

Launch controlled phishing email campaigns

Track employee actions (open, click, ignore, report)

Redirect unsafe actions to warning and awareness pages

Deliver short quizzes after risky behavior

Generate campaign reports and analytics

Export campaign results as PDF reports

The platform is implemented using PHP, SQLite, HTML/CSS, and runs in a virtualized lab environment.

System Architecture

EnginGuard follows a simple web-based architecture:

Frontend:
Public pages (Home, About, How It Works, Contact) and admin dashboards styled using reusable CSS files.

Backend:
PHP logic handles authentication, campaign management, email sending, event tracking, and reporting.

Database:
SQLite database (enginguard.db) stores users, campaigns, targets, and interaction events.

Email Integration:
Phishing emails are delivered through a configured SMTP server (e.g., HMailServer in a lab environment).

Main Features

Phishing Campaign Management

Create and schedule campaigns

Select email templates and target users

Automatic campaign status handling

Employee Interaction Tracking

Open, click, ignore, and report events

Warning page for unsafe clicks

Awareness content and quiz redirection

Admin Dashboard & Reports

Real-time campaign statistics

User-level and campaign-level analytics

Exportable PDF reports

Awareness & Training

Warning page for risky actions

Awareness page with security guidance

Quiz page to reinforce learning

Repository Structure (Overview)

The repository contains the following key components:

Public Pages

home.php, about.php, how-it-works.php, contact.php

Authentication

login.php, logout.php, auth_admin.php

Admin Pages

dashboard.php

campaigns.php, launch_campaign.php, edit_campaign.php

users.php, add_user.php

reports.php, report.php

Training Pages

warning.php

awareness.php

quiz.php

Utilities & Logic

db.php (database connection)

campaign_utils.php

send_campaign_email.php

export_pdf.php

Styling

Page-specific CSS files (e.g., home.css, campaigns.css, login.css)

Shared styles (header.css, admin.css)

Database

enginguard.db (SQLite database)

Local Development Setup
Requirements

PHP 8.0+

Apache or equivalent web server

SQLite enabled in php.ini

Git

Ensure the following PHP extensions are enabled:

extension=sqlite3
extension=zip

Installation Steps

Clone the Repository

git clone https://github.com/<your-username>/EnginGuard.git
cd EnginGuard


Move to Web Directory
Place the project inside your web server root (e.g. htdocs or /var/www/html).

Database
Ensure enginguard.db exists in the project root and is writable by the server.

Configure Email
Update email_config.php with your SMTP or lab mail server details.

Access the Platform
Open in browser:

http://localhost/EnginGuard/home.php

Admin Access

Only users with the admin role can access the dashboard.

Login is handled via login.php.

Session-based authentication protects admin pages.

Reporting & Analytics

Campaign interaction data is stored automatically

Admins can view:

Click rates

Reported emails

Ignored campaigns

Full campaign reports can be exported as PDF files

Academic Context

This project was developed as part of an academic cybersecurity project focusing on:

Social engineering awareness

Human-centric cybersecurity risks

Secure system design

Practical phishing simulation in a controlled environment

Contributing

Fork the repository

Create a feature branch

git checkout -b feature/YourFeature


Commit changes

git commit -m "Add new feature"


Push to GitHub

git push origin feature/YourFeature


Open a Pull Request

Acknowledgements

Bahrain Polytechnic

Phishing awareness and cybersecurity research resources

Open-source PHP and SQLite communities
