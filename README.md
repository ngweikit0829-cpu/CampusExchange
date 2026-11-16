# CampusExchange

A campus second-hand e-commerce platform where students can buy and sell items safely within and across campuses.

Final Year Project for the Diploma in Software Engineering (E-Commerce & Financial Technology), New Era Institute of Vocational & Continuing Education.

![alt text](image.png)
![alt text](image-1.png)
![alt text](image-2.png)

## Features

- **Multi-campus email verification** with dynamic university branding
- **Item management** with deterministic filtering and pagination
- **Internal virtual wallet** with an **escrow payment mechanism** that holds funds until the transaction is completed
- **Item-bound real-time messaging** between buyer and seller using AJAX polling
- **Admin governance dashboard** for managing users, listings and platform activity

## Tech Stack

| Layer | Technology |
| --- | --- |
| Presentation | HTML, CSS, JavaScript (AJAX) |
| Application | PHP 8.1+ |
| Data | MySQL 8.0+ (accessed through PDO) |

## Architecture

CampusExchange follows a **Three-Tier Client-Server Architecture**:

1. **Presentation tier**: browser-based user interface
2. **Application tier**: PHP business logic, authentication, wallet and messaging
3. **Data tier**: MySQL database

## Security

- Passwords hashed with **BCrypt**
- **PDO prepared statements** to prevent SQL injection
- Input sanitization to prevent **Cross-Site Scripting (XSS)**

## Project Structure

| Path | Purpose |
| --- | --- |
| `index.php`, `register.php`, `auth.php`, `logout.php` | Landing page, registration, authentication and logout |
| `home.php`, `product.php`, `get_products.php` | Browse, filter and view items |
| `post_product.php`, `my_listings.php` | Create and manage your own listings |
| `purchase_order.php`, `orders.php`, `complete_order.php`, `cancel_order.php` | Order flow with escrow |
| `top_up.php`, `wallet_history.php` | Virtual wallet and transaction history |
| `chat.php`, `chat_messages.php` | Item-bound messaging (AJAX polling) |
| `seller.php`, `submit_review.php`, `watchlist.php`, `profile.php` | Seller pages, reviews, watchlist and profile |
| `admin/` | Admin governance dashboard |
| `includes/`, `assets/`, `uploads/products/` | Shared code, static files and uploaded item images |
| `config.php` | Database configuration |

## Getting Started

### Requirements

- PHP 8.1 or later
- MySQL 8.0 or later
- [XAMPP](https://www.apachefriends.org/) (Apache and MySQL)

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/ngweikit0829-cpu/CampusExchange.git
   ```
2. Start **Apache** and **MySQL** in the XAMPP Control Panel, open phpMyAdmin (`http://localhost/phpmyadmin`), create a new database, and import `data/campus_ecommerce.sql`.
3. Open `config.php` and set the database host, name, username and password to match your XAMPP setup (the default XAMPP user is `root` with an empty password).
4. Copy the project files into the XAMPP `htdocs` folder and open `http://localhost/index.php` in your browser.

## Author

**Ng Wei Kit**

