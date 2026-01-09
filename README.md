# Step CA GUI

A simple web interface for managing certificates with [Smallstep CA](https://smallstep.com/docs/step-ca).

## Features

- Generate TLS certificates with custom hostnames
- Support for Subject Alternative Names (SANs) - DNS names and IP addresses
- Download generated certificates and private keys
- Simple and clean web interface

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

If you want to initialize a new CA (instead of using existing secrets):

1. Remove existing step-data:
   ```bash
   rm -rf step-data/*
   ```

2. Start the services - step-ca will auto-initialize:
   ```bash
   docker compose up -d
   ```

3. Get the generated admin password from logs:
   ```bash
   docker logs step-ca 2>&1 | grep "administrative password"
   ```

4. Update `docker-compose.yml` with the password:
   ```yaml
   environment:
     - CA_PASSWORD=<your-password-here>
   ```

5. Recreate the web container:
   ```bash
   docker compose up -d --force-recreate web-gui
   ```

## Configuration

Environment variables in `docker-compose.yml`:

| Variable | Description | Default |
|----------|-------------|---------|
| `CA_URL` | Step CA server URL | `https://step-ca:9000` |
| `CA_PROVISIONER` | Provisioner name | `admin` |
| `CA_PASSWORD` | Provisioner password | - |
| `STEP_ROOT` | Path to root CA certificate | `/home/step/certs/root_ca.crt` |

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
- The provisioner password should be kept secure and not committed in plain text
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
