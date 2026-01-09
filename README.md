# Step CA GUI

A web interface for managing certificates with [Smallstep CA](https://smallstep.com/docs/step-ca).

## Features

- Generate TLS certificates with custom hostnames and SANs
- Configurable certificate validity (24h to 1 year)
- Certificate management: view details, renew, revoke, delete
- Export certificates as PFX/P12 for Windows/Java
- Search and filter certificates
- Dark mode support
- Audit logging of all operations
- REST API for automation
- Password-protected interface (except CA downloads)

## Prerequisites

- Docker
- Docker Compose

## Quick Start

1. **Clone the repository**
   ```bash
   git clone https://gitlab.safehost.com/linux/step-ca-gui.git
   cd step-ca-gui
   ```

2. **Decrypt the CA secrets** (if using existing CA)
   ```bash
   cd step-data/secrets
   openssl enc -aes-256-cbc -d -pbkdf2 -in intermediate_ca_key.enc -out intermediate_ca_key
   openssl enc -aes-256-cbc -d -pbkdf2 -in root_ca_key.enc -out root_ca_key
   openssl enc -aes-256-cbc -d -pbkdf2 -in password.enc -out password
   ```

3. **Start the services**
   ```bash
   docker compose up -d
   ```

4. **Access the web interface**

   Open http://localhost:8080 in your browser.

## Fresh Installation

If you want to initialize a new CA:

1. Remove existing step-data:
   ```bash
   rm -rf step-data/*
   ```

2. Start the services - step-ca will auto-initialize:
   ```bash
   docker compose up -d
   ```

3. The password is automatically saved in `step-data/secrets/password`

## Configuration

Environment variables in `docker-compose.yml`:

| Variable | Description | Default |
|----------|-------------|---------|
| `CA_URL` | Step CA server URL | `https://step-ca:9000` |
| `CA_PROVISIONER` | Provisioner name | `admin` |
| `CA_PASSWORD_FILE` | Path to password file | `/home/step/secrets/password` |
| `STEP_ROOT` | Path to root CA certificate | `/home/step/certs/root_ca.crt` |

## Authentication

The web interface is password protected (except for CA certificate downloads).

**Default credentials:** `admin` / `admin`

### Managing Users

The password file is located at `www/.passwd`. Format: `username:password_hash`

```bash
# Add a new user
php -r "echo 'newuser:' . password_hash('newpassword', PASSWORD_DEFAULT) . PHP_EOL;" >> www/.passwd

# Generate new password hash
php -r "echo password_hash('newpassword', PASSWORD_DEFAULT);"
```

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

- CA private keys are stored encrypted (AES-256-CBC) in the repository
- Unencrypted keys are excluded from git via `.gitignore`
- Password and API key files are excluded from git
- Generated certificates are stored in `www/certs/` (excluded from git)

## Encrypting Secrets

To encrypt secrets before committing:

```bash
cd step-data/secrets
openssl enc -aes-256-cbc -salt -pbkdf2 -in intermediate_ca_key -out intermediate_ca_key.enc
openssl enc -aes-256-cbc -salt -pbkdf2 -in root_ca_key -out root_ca_key.enc
openssl enc -aes-256-cbc -salt -pbkdf2 -in password -out password.enc
```

## License

Internal use only.
