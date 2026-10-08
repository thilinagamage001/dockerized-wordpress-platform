# Dockerized WordPress Platform

![Docker](https://img.shields.io/badge/Docker-2496ED?style=flat-square&logo=docker&logoColor=white)
![Docker Compose](https://img.shields.io/badge/Docker_Compose-2496ED?style=flat-square&logo=docker&logoColor=white)
![Nginx](https://img.shields.io/badge/Nginx-009639?style=flat-square&logo=nginx&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-21759B?style=flat-square&logo=wordpress&logoColor=white)
![PHP 8.2-FPM](https://img.shields.io/badge/PHP_8.2--FPM-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white)

A production-style WordPress stack built with Docker Compose — running **Nginx**, **WordPress (PHP 8.2-FPM)**, **MySQL**, and **phpMyAdmin** as four decoupled containers on an isolated internal bridge network, with persistent storage managed through named volumes and host bind mounts.

---

## Architecture

<img width="1376" height="768" alt="architecture-diagram" src="https://github.com/user-attachments/assets/84e7ade3-6d6c-4bec-88a0-edd5071dc11d" />


### Request & Service Flow

1. **Edge Routing (`nginx`)**: Client HTTP requests on port `8080` hit the Alpine Nginx container. Static assets (CSS, JS, images, fonts) are served directly by Nginx from a read-only mount of the WordPress volume and `/wp-content`.
2. **FastCGI Application Processing (`wordpress`)**: Dynamic `.php` requests are forwarded by Nginx over the internal Docker bridge network to `wordpress:9000` (PHP 8.2-FPM on Alpine Linux).
3. **Database Layer (`database` & `phpmyadmin`)**: Both WordPress and phpMyAdmin (`localhost:8081`) communicate with MySQL (`database:3306`) over the internal network.
4. **Healthcheck-Gated Startup**: MySQL runs a periodic `mysqladmin ping` healthcheck. Using `depends_on` with `condition: service_healthy`, WordPress and phpMyAdmin wait until MySQL is ready to accept connections before starting.

---

## Services

| Service | Image | Container Name | Host → Container Port | Role |
| :--- | :--- | :--- | :--- | :--- |
| **nginx** | `nginx:stable-alpine` | `${CONTAINER_NAME}-nginx` | `8080:80` | Reverse proxy, serves static files, forwards PHP via FastCGI |
| **wordpress** | `wordpress:7.0.1-php8.2-fpm-alpine` | `wordpress` | `9000` *(internal only)* | PHP 8.2-FPM application runtime |
| **database** | `mysql:latest` | `${CONTAINER_NAME}-db` | `3306:3306` | Relational database with readiness healthcheck |
| **phpmyadmin** | `phpmyadmin/phpmyadmin` | `${CONTAINER_NAME}-phpmyadmin` | `8081:80` | Web-based MySQL administration UI |

All services run on the `internal` bridge network and are configured with `restart: unless-stopped`.

---

## Storage & Persistence

| Volume / Mount | Type | Container Path | Used By | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| `dbdata` | Named volume | `/var/lib/mysql` | `database` (`rw`) | Persistent MySQL database storage |
| `wordpress` | Named volume | `/var/www/html` | `wordpress` (`rw`), `nginx` (`ro`) | WordPress core application files |
| `./wp-config.php` | Bind mount | `/var/www/html/wp-config.php` | `wordpress` (`rw`) | Custom Docker-aware WordPress configuration |
| `./wp-content` | Bind mount | `/var/www/html/wp-content` | `wordpress` (`rw`), `nginx` (`rw`) | Themes, plugins, and media uploads |
| `./nginx` | Bind mount | `/etc/nginx/conf.d` | `nginx` (`rw`) | Nginx server block configuration (`default.conf`) |

Database records, WordPress core files, and custom themes/plugins persist across container restarts and recreation.

---

## Project Structure

```text
dockerized-wordpress-platform/
├── nginx/
│   └── default.conf          # Nginx virtual host, FastCGI proxy & security rules
├── wp-content/
│   ├── plugins/              # WordPress plugins (bind-mounted)
│   └── themes/               # WordPress themes (bind-mounted)
├── .env.example              # Template for environment variables
├── .gitignore                # Excludes .env, uploads, caches, and SQL dumps
├── docker-compose.yml        # Multi-container orchestration & network definition
├── wp-config.php             # Environment-driven WordPress configuration
└── README.md                 # Project documentation
```

---

## Key Configuration Details

- **Nginx (`nginx/default.conf`)**:
  - Uses `try_files $uri $uri/ /index.php?$args;` to support WordPress pretty permalinks natively.
  - Proxies `.php` scripts to `fastcgi_pass wordpress:9000` with `try_files $uri =404;` to prevent arbitrary script execution.
  - Sets `client_max_body_size 64M;` to allow larger theme, plugin, and media uploads.
  - Blocks public access to hidden dotfiles (`location ~ /\.(?!well-known).* { deny all; }`).
- **WordPress (`wp-config.php`)**:
  - Connects to `database:3306` using environment variables loaded via a `getenv_docker()` helper (supporting both plain env vars and `*_FILE` Docker secrets).
  - Sets `define('FS_METHOD', 'direct');` to enable direct plugin and theme installation without FTP prompts.
  - Detects `HTTP_X_FORWARDED_PROTO` to support SSL-terminating reverse proxies.
- **Database Healthcheck (`docker-compose.yml`)**:
  - Executes `mysqladmin ping -h localhost` every `10s` (timeout `5s`, `5` retries) and gates `wordpress` and `phpmyadmin` startup via `depends_on: condition: service_healthy`.

---

## Getting Started

### Prerequisites

- [Docker Engine](https://docs.docker.com/engine/install/) (v20.10+)
- [Docker Compose](https://docs.docker.com/compose/install/) (v2.0+)

### Installation

**1. Clone the repository**

```bash
git clone https://github.com/thilinagamage001/dockerized-wordpress-platform.git
cd dockerized-wordpress-platform
```

**2. Configure environment variables**

Copy the example environment file and customize your credentials:

```bash
cp .env.example .env
```

```dotenv
CONTAINER_NAME=wp-platform
DATABASE_NAME=wordpress
DATABASE_USER=wordpress
DATABASE_PASSWORD=your_secure_password
DATABASE_ROOT_PASSWORD=your_secure_root_password
```

**3. Start the stack**

```bash
docker compose up -d
```

**4. Verify container status & health**

```bash
docker compose ps
```

**5. Access the platform**

- **WordPress Site**: [http://localhost:8080](http://localhost:8080)
- **WordPress Admin**: [http://localhost:8080/wp-admin](http://localhost:8080/wp-admin)
- **phpMyAdmin**: [http://localhost:8081](http://localhost:8081)

---

## Useful Commands

```bash
# Start all services in detached mode
docker compose up -d

# Stop and remove containers (preserves named volumes and data)
docker compose down

# Stop containers AND delete named volumes (resets database & WP core)
docker compose down -v

# Follow logs across all services
docker compose logs -f

# Follow logs for a specific service (e.g., nginx or wordpress)
docker compose logs -f nginx

# Reload Nginx configuration without downtime
docker compose exec nginx nginx -s reload

# Open an interactive shell inside the WordPress container
docker compose exec wordpress sh
```

---

## Database Backup & Restore

**Export a database dump (`backup.sql`):**

```bash
docker compose exec -T database mysqldump -u root -p"${DATABASE_ROOT_PASSWORD}" wordpress > backup.sql
```

*(Or prompt interactively for the password:)*

```bash
docker compose exec database mysqldump -u root -p wordpress > backup.sql
```

**Restore from a database dump:**

```bash
docker compose exec -T database mysql -u root -p wordpress < backup.sql
```

---

## Learning Objectives

This project was built to practice and demonstrate:

- Containerizing a decoupled WordPress stack with Docker Compose
- Isolating multi-container communication on a custom bridge network
- Configuring Nginx as a static file server and FastCGI reverse proxy to PHP-FPM
- Designing hybrid persistence with named volumes and host bind mounts
- Managing environment-driven configuration and secrets cleanly
- Orchestrating container startup order with database healthchecks

---

## Author

**Thilina Gamage**

- GitHub: [@thilinagamage001](https://github.com/thilinagamage001)
- LinkedIn: [thilinagamage001](https://linkedin.com/in/thilinagamage001)

---

If you find this project useful, feel free to ⭐ star the repository!
