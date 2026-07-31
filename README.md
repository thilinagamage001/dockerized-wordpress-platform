# WordPress Docker DevOps Platform

A production-style containerized WordPress environment built using Docker Compose.  
This project demonstrates how to run WordPress with a modern infrastructure approach using containers, Nginx, PHP-FPM, MySQL, and CI/CD automation.

The goal of this project is to build a reproducible WordPress development and deployment workflow following DevOps best practices.

---

## 🏗️ Architecture

```
                    Users
                      |
                      |
                  Nginx
              (Reverse Proxy)
                      |
                      |
              WordPress PHP-FPM
                      |
                      |
                  MySQL
               (Database)

```

---

## 🚀 Technologies Used

| Technology | Purpose |
|------------|---------|
| Docker | Containerization |
| Docker Compose | Multi-container orchestration |
| Nginx | Web server / Reverse proxy |
| WordPress | CMS Application |
| PHP-FPM | PHP application processing |
| MySQL | Database |
| phpMyAdmin | Database management |
| Git | Version control |
| GitHub Actions | CI/CD automation |

---

# 📁 Project Structure

```
wordpress-docker-devops/
│
├── .github/
│   └── workflows/
│       └── ci.yml
│
├── docker-compose.yml
├── .env.example
│
├── nginx/
│   └── default.conf
│
├── wp-content/
│   ├── themes/
│   │   └── custom-theme/
│   │
│   ├── plugins/
│   │   └── custom-plugin/
│   │
│   └── uploads/
│
└── README.md

```

---

# 🐳 Docker Services

## Nginx

Responsible for:

- Handling HTTP requests
- Serving static files
- Passing PHP requests to WordPress PHP-FPM

Port:

```
8080
```

---

## WordPress

Runs using:

```
wordpress:7.0.1-php8.2-fpm-alpine
```

Features:

- PHP-FPM based WordPress
- Custom themes support
- Custom plugin development
- Persistent application files

---

## MySQL Database

Stores:

- WordPress settings
- Users
- Posts
- Plugin data
- Theme settings

---

## phpMyAdmin

Database administration interface.

Access:

```
http://localhost:8081
```

---

# ⚙️ Installation

## 1. Clone Repository

```bash
git clone https://github.com/thilinagamage001/wordpress-docker-devops.git

cd wordpress-docker-devops
```

---

## 2. Create Environment File

Copy:

```bash
cp .env.example .env
```

Update database credentials:

```env
DATABASE_NAME=wordpress
DATABASE_USER=wordpress
DATABASE_PASSWORD=password
DATABASE_ROOT_PASSWORD=rootpassword
```

---

## 3. Start Containers

Build and start:

```bash
docker compose up -d --build
```

Check running containers:

```bash
docker ps
```

---

## 4. Access Application

WordPress:

```
http://localhost:8080
```

phpMyAdmin:

```
http://localhost:8081
```

---

# 🛠️ Development Workflow

## Themes

Custom themes are developed inside:

```
wp-content/themes/
```

Example:

```
wp-content/themes/my-theme
```

Open with VS Code:

```bash
code wp-content/themes/my-theme
```

---

## Plugins

Custom plugins are developed inside:

```
wp-content/plugins/
```

---

## Database

Database data is stored using Docker volumes:

```
dbdata
```

This keeps data persistent after container recreation.

---

# 🔐 Environment Security

Sensitive data is not committed.

The following files are ignored:

```
.env
wp-content/uploads/
```

Use:

```
.env.example
```

for sharing required configuration.

---

# 🔄 CI/CD Pipeline

The project uses GitHub Actions to automate:

## Continuous Integration

On every push:

```
GitHub Push
      |
      |
GitHub Actions
      |
      |
Validate Docker Compose
      |
      |
PHP Syntax Check
```

---

## Continuous Deployment (Planned)

Future deployment workflow:

```
Developer
    |
    |
GitHub
    |
    |
GitHub Actions
    |
    |
Docker Image / SSH Deployment
    |
    |
Production Server

```

---

# 📝 Useful Docker Commands

## Start services

```bash
docker compose up -d
```

---

## Stop services

```bash
docker compose down
```

---

## View logs

```bash
docker compose logs -f
```

---

## Restart WordPress

```bash
docker compose restart wordpress
```

---

## Enter WordPress container

```bash
docker exec -it wordpress sh
```

---

# 📦 Backup Strategy

## Database Backup

Example:

```bash
docker exec database \
mysqldump -u root -p wordpress > backup.sql
```

---

## Restore Database

```bash
mysql -u root -p wordpress < backup.sql
```

---

# 🚧 Future Improvements

- [ ] AWS EC2 deployment
- [ ] HTTPS with Let's Encrypt
- [ ] Automated backups
- [ ] Terraform infrastructure
- [ ] Ansible server configuration
- [ ] Docker image registry
- [ ] Kubernetes deployment
- [ ] Prometheus monitoring
- [ ] Grafana dashboards
- [ ] Security scanning

---

# 🎯 Learning Objectives

This project demonstrates practical knowledge of:

- Containerizing WordPress applications
- Managing multi-container environments
- Linux permissions
- Reverse proxy configuration
- Database persistence
- Environment management
- CI/CD fundamentals
- Infrastructure automation

---

# 👨‍💻 Author

**Thilina Gamage**

GitHub:
https://github.com//thilinagamage001

LinkedIn:
https://linkedin.com/in/thilinagamage001

---

⭐ If you find this project useful, feel free to star the repository.
