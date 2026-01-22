# Collection Tracking System

A Progressive Web Application (PWA) for tracking daily collections by agents for a large supplier.

## Features

### For Admins:
- **Dashboard**: View total collections, agents in transit, completed orders
- **Assignments**: Assign shops and regions to agents, import via Excel
- **Agents**: Monitor collection status, pending items for each agent
- **Management**: Add/remove agents and admins
- **Store Data**: Add/remove stores and regions, approve bank submissions
- **Export**: Download daily and weekly reports in CSV format

### For Agents:
- **Dashboard**: View daily collection targets, stores assigned, progress percentage
- **Store**: Record collections, upload receipt images, add comments
- **Submissions**: Submit bank deposits with receipts for approval

## Technical Features

- **PWA Support**: Installable on mobile devices with offline capabilities
- **Offline First**: Data saved locally every 5 minutes
- **Automatic Cleanup**: Local data deleted every other day
- **Responsive Design**: Works on desktop and mobile devices
- **Secure Authentication**: Session-based authentication with role management

## Installation

1. Make sure you have XAMPP installed with PHP and MySQL
2. Place this project in your XAMPP `htdocs` directory
3. Start Apache and MySQL through XAMPP Control Panel
4. Access the application via `http://localhost/collection-tracking-system`
5. Login with default credentials:
   - Username: `admin`
   - Password: `admin123`

## Default Credentials

- **Admin**: username: `admin`, password: `admin123`
- **Agents**: Created through admin panel with custom credentials

## Security Notes

- The system includes basic authentication and authorization
- Passwords are hashed using PHP's password_hash function
- Input validation is implemented throughout the application
- File uploads are restricted to safe formats (images and PDFs)

## Customization

- Modify `config.php` to update database settings if needed
- Adjust styling in `css/style.css`
- Add additional security measures as needed for production deployment