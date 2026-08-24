# store-commerce: PHP E-STORE

An e-commerce web application developed using a monolithic PHP architecture, structured MySQL database operations, and dynamic front-end scripts.

## Key Features

* **User Authentication**: Secure user registration and login workflows backed by PHP session management rules.
* **Shopping Core**: Complete transactional support for interactive shopping carts and order processing validations.
* **Administrative Control**: Unified portal managing product creations, dynamic inventory logs, and system metrics.
* **Advanced Data Navigation**: Built-in product search modules paired with functional multi-parameter data filtering.
* **Intelligent Product Tagging**: Automated category routing utilizing a rule-based simulated AI classification engine.

## Repository Structure

```text
├── README.md         # Technical platform overview and deployment documentation
├── database.sql      # Core relational database schemas and lookup tables
├── index.php         # Primary engine routing and centralized page views
├── script.js         # Client-side dynamic item rendering and cart mechanics
└── style.css         # Semantic layout theme elements and visual assets
```

## Technologies Used

* **Backend Engine**: PHP 8.x
* **Database Management**: MySQL (via secure PHP Data Objects - PDO layer)
* **Design Layers**: Structural HTML5 and custom CSS3 tokens
* **Dynamic Interactivity**: Vanilla JavaScript ES6 Event Handlers

##  Deployment & Environment Verification

### 1. Database Implementation
Initialize your server database environment and seed your structural mapping assets:
```bash
mysql -u root -p secure_store < database.sql
```

### 2. Local Environment Execution
Relocate the `store-commerce` project directory into your local web root path (e.g., WampServer `www` or XAMPP `htdocs`). Navigate to your storefront portal via the browser:
```text
http://localhost/store-commerce/index.php
```
