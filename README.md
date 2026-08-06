# Dockerized WordPress Platform

A production-style WordPress stack built with Docker Compose  Nginx, PHP-FPM, MySQL, and phpMyAdmin running as four containers on a single internal bridge network, with persistent storage via named volumes and bind mounts.

## Architecture


<img width="967" height="966" alt="Docker WordPress" src="https://github.com/user-attachments/assets/4697ad4e-c89b-45b2-a98d-c33a38073706" />


Nginx serves static files directly and forwards `.php` requests to WordPress over FastCGI. WordPress and phpMyAdmin both talk to MySQL over the internal network.

## Services

| Service | Image | Container name | Ports | Role |
|---|---|---|---|---|
| Nginx | `nginx:stable-alpine` | `${CONTAINER_NAME}-nginx` | `8080:80` | Reverse proxy, serves static files, forwards PHP requests |
| WordPress | `wordpress:7.0.1-php8.2-fpm-alpine` | `wordpress` | `9000` (internal) | PHP-FPM application server |
| MySQL | `mysql:latest` | `${CONTAINER_NAME}-db` | `3306:3306` | Database |
| phpMyAdmin | `phpmyadmin/phpmyadmin` | `${CONTAINER_NAME}-phpmyadmin` | `8081:80` | Database admin UI |

All services run on an internal `bridge` network and restart with `unless-stopped`.

## Storage

| Volume / mount | Purpose | Used by |
|---|---|---|
| `dbdata` (named) | MySQL data files (`/var/lib/mysql`) | MySQL |
| `wordpress` (named) | WordPress core files (`/var/www/html`) | WordPress, Nginx (read-only) |
| `./wp-content` (bind, rw) | Themes, plugins, uploads | WordPress, Nginx |
| `./nginx` (bind, rw) | `default.conf` → `/etc/nginx/conf.d` | Nginx |

Database data and WordPress core/content persist across container recreation.

## Configuration

- **Nginx** (`nginx/default.conf`): `try_files $uri $uri/ /index.php?$args;`, with the PHP location block proxying to `fastcgi_pass wordpress:9000` and `SCRIPT_FILENAME=$document_root$fastcgi_script_name`.
- **WordPress**: connects to `database:3306` using environment variables. `wp-config.php` reads `*_FILE` / plain env vars via a custom `getenv_docker()` helper.
- **phpMyAdmin**: configured with `PMA_HOST=database` and `PMA_PORT=3306`.
- **MySQL healthcheck**: `mysqladmin ping -h localhost`, checked every 10 seconds.

## Environment variables

No `.env` or `.env.example` is committed to the repo  you need to create your own `.env` file (used via `env_file: .env` on every service) with:

```
CONTAINER_NAME=your-project-name
DATABASE_NAME=wordpress
DATABASE_USER=wordpress
DATABASE_PASSWORD=your_password
DATABASE_ROOT_PASSWORD=your_root_password
```

## Installation

**1. Clone the repository**

```bash
git clone https://github.com/thilinagamage001/dockerized-wordpress-platform.git
cd dockerized-wordpress-platform
```

**2. Create your `.env` file**

Create a `.env` file in the project root with the variables listed above.

**3. Start the stack**

```bash
docker compose up -d --build
```

**4. Check running containers**

```bash
docker ps
```

**5. Access the application**

- WordPress: [http://localhost:8080](http://localhost:8080)
- phpMyAdmin: [http://localhost:8081](http://localhost:8081)

## Useful commands

```bash
# Start services
docker compose up -d

# Stop services
docker compose down

# View logs
docker compose logs -f

# Restart WordPress
docker compose restart wordpress

# Enter the WordPress container
docker exec -it wordpress sh
```

## Backup & restore

**Backup database**

```bash
docker exec ${CONTAINER_NAME}-db mysqldump -u root -p wordpress > backup.sql
```

**Restore database**

```bash
docker exec -i ${CONTAINER_NAME}-db mysql -u root -p wordpress < backup.sql
```


## Learning objectives

This project was built to practice:

- Containerizing a WordPress application with Docker Compose
- Managing multi-container environments on a shared internal network
- Configuring Nginx as a reverse proxy with FastCGI to PHP-FPM
- Managing persistent storage with named volumes and bind mounts
- Environment-based configuration and secrets handling
- Database healthchecks and service dependencies

## Author

**Thilina Gamage**

- GitHub: [thilinagamage001](https://github.com/thilinagamage001)
- LinkedIn: [thilinagamage001](https://linkedin.com/in/thilinagamage001)

---

If you find this project useful, feel free to star the repository.
