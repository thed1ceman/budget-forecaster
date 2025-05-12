# Payment Planner

A secure web application that helps users manage their recurring payments and financial planning.

## Features
- Track recurring payments and their due dates
- Calculate remaining bills based on current date
- Account for weekends in payment scheduling
- Calculate remaining balance after bills
- Secure user authentication
- Data encryption

## Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer for dependency management

## Installation
1. Clone the repository
2. Run `composer install` to install dependencies
3. Create a MySQL database
4. Import the database schema from `database/schema.sql`
5. Copy `config/config.example.php` to `config/config.php` and update the database credentials
6. Ensure the web server has write permissions to the `logs` directory

## Security Features
- Password hashing using bcrypt
- Prepared statements for all database queries
- CSRF protection
- XSS prevention
- Input validation and sanitization
- Secure session handling

## License
MIT License 