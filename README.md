# Step CA Light GUI

A lightweight web interface for managing certificates with [Smallstep CA](https://smallstep.com/docs/step-ca).

## Features

- Generate TLS certificates with custom hostnames and SANs
- Configurable certificate validity (24h to 1 year, max 10 years)
- Certificate management: view details, renew, revoke, delete
- Export certificates as PFX/P12 for Windows/Java
- Search and filter certificates
- Dark mode support
- Audit logging of all operations
- REST API for automation
- Session-based authentication with login page
- User management via CLI

## Screenshots

![Certificate Generator](docs/screenshot-generator.png)
![Certificate List](docs/screenshot-list.png)

## Prerequisites

- Docker
- Docker Compose

## Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/needsone/step-ca-light-gui.git
   cd step-ca-light-gui
   ```

2. **Start the services**
   ```bash
   docker compose up -d
   ```

3. **Create an admin user**
   ```bash
   docker exec -it step-ca-gui php /var/www/html/user-cli.php add admin
   ```

4. **Access the web interface**

   Open http://localhost:8080 in your browser.

## Configuration

Environment variables in `docker-compose.yml`:

| Variable | Description | Default |
|----------|-------------|---------|
| `CA_URL` | Step CA server URL | `https://step-ca:9000` |
| `CA_PROVISIONER` | Provisioner name | `admin` |
| `CA_PASSWORD_FILE` | Path to password file | `/home/step/secrets/password` |
| `STEP_ROOT` | Path to root CA certificate | `/home/step/certs/root_ca.crt` |
| `CA_NAME` | Name of the CA | `Step CA` |

### Certificate Duration

The CA is configured with:
- **Minimum duration**: 5 minutes
- **Default duration**: 1 year (8760h)
- **Maximum duration**: 10 years (87600h)

## Authentication

The web interface uses session-based authentication with a login page. CA certificate downloads remain public (for easy client setup).

### User Management CLI

Manage users via the command line:

```bash
# Add a new user (will prompt for password)
docker exec -it step-ca-gui php /var/www/html/user-cli.php add <username>

# Change a user's password
docker exec -it step-ca-gui php /var/www/html/user-cli.php passwd <username>

# Delete a user
docker exec -it step-ca-gui php /var/www/html/user-cli.php delete <username>

# List all users
docker exec -it step-ca-gui php /var/www/html/user-cli.php list
```

### Password Requirements

- Minimum 8 characters
- Username must start with a letter
- Username can contain letters, numbers, underscores and hyphens

### Open Access Mode

If no users exist in the password file, authentication is disabled (open access).

## REST API

The API supports both API Key and Basic Auth authentication.

### Setup API Keys

```bash
# Generate a random API key
openssl rand -hex 32

# Add to www/.apikeys file
echo "myapp:your_generated_key_here" >> www/.apikeys
```

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/certificates` | List all certificates |
| POST | `/api/certificates` | Generate new certificate |
| GET | `/api/certificates/{name}` | Get certificate details |
| DELETE | `/api/certificates/{name}` | Delete certificate |
| POST | `/api/certificates/{name}/revoke` | Revoke certificate |
| POST | `/api/certificates/{name}/renew` | Renew certificate |
| POST | `/api/certificates/{name}/export` | Export as PFX |

### Examples

```bash
# List certificates
curl -H "X-API-Key: your_key" http://localhost:8080/api/certificates

# Generate certificate
curl -X POST -H "X-API-Key: your_key" -H "Content-Type: application/json" \
  -d '{"hostname":"server.example.com","sans":["192.168.1.100"],"duration":"720h"}' \
  http://localhost:8080/api/certificates

# Get details
curl -H "X-API-Key: your_key" http://localhost:8080/api/certificates/server.example.com

# Export PFX
curl -X POST -H "X-API-Key: your_key" -H "Content-Type: application/json" \
  -d '{"password":"export123"}' \
  http://localhost:8080/api/certificates/server.example.com/export -o cert.p12

# Delete
curl -X DELETE -H "X-API-Key: your_key" \
  http://localhost:8080/api/certificates/server.example.com
```

## Audit Logs

All operations are logged to `www/logs/audit.json`. View logs at http://localhost:8080/logs.php

Logged actions:
- User login / logout
- Certificate generation
- Certificate download
- Certificate deletion
- Certificate revocation
- Certificate renewal
- PFX export
- API access

## Architecture

```
┌─────────────────┐     ┌─────────────────┐
│   Web Browser   │────▶│   web-gui       │
│                 │     │   (PHP/Apache)  │
└─────────────────┘     │   :8080         │
                        └────────┬────────┘
                                 │
                                 ▼
                        ┌─────────────────┐
                        │   step-ca       │
                        │   (Smallstep)   │
                        │   :9000         │
                        └─────────────────┘
```

## Security Notes

- Passwords are hashed with bcrypt
- Session cookies are HTTP-only
- CA private keys should be kept secure
- Password and API key files are excluded from git
- Generated certificates are stored in `www/certs/`

## Development

### Project Structure

```
├── docker-compose.yml    # Docker services configuration
├── Dockerfile            # Web GUI container
├── init-step-ca.sh       # CA initialization script
├── www/                  # Web application
│   ├── index.php         # Certificate generator
│   ├── login.php         # Login page
│   ├── download-cert.php # Certificate list
│   ├── cert-details.php  # Certificate details
│   ├── logs.php          # Audit logs viewer
│   ├── api/              # REST API
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript
│   └── lib/              # PHP libraries
└── step-data/            # CA data (gitignored)
```

### Running Locally

```bash
# Build and start
docker compose up -d --build

# View logs
docker compose logs -f

# Stop
docker compose down
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Acknowledgments

- [Smallstep](https://smallstep.com/) for the excellent Step CA
- All contributors to this project
