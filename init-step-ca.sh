#!/bin/sh
set -e

STEP_PATH="/home/step"
CONFIG_FILE="$STEP_PATH/config/ca.json"
PASSWORD_FILE="$STEP_PATH/secrets/password"
SECRETS_DIR="$STEP_PATH/secrets"

# CA Configuration (can be overridden by environment variables)
CA_NAME="${CA_NAME:-Vaultica CA}"
CA_DNS="${CA_DNS:-step-ca,localhost}"
CA_ADDRESS="${CA_ADDRESS:-:9000}"
CA_PROVISIONER="${CA_PROVISIONER:-admin}"

# Check for encrypted files that need decryption
check_encrypted_files() {
    local needs_decrypt=0

    if [ -f "$SECRETS_DIR/intermediate_ca_key.enc" ] && [ ! -f "$SECRETS_DIR/intermediate_ca_key" ]; then
        echo "WARNING: intermediate_ca_key.enc exists but not decrypted"
        needs_decrypt=1
    fi

    if [ -f "$SECRETS_DIR/root_ca_key.enc" ] && [ ! -f "$SECRETS_DIR/root_ca_key" ]; then
        echo "WARNING: root_ca_key.enc exists but not decrypted"
        needs_decrypt=1
    fi

    if [ -f "$SECRETS_DIR/password.enc" ] && [ ! -f "$SECRETS_DIR/password" ]; then
        echo "WARNING: password.enc exists but not decrypted"
        needs_decrypt=1
    fi

    if [ $needs_decrypt -eq 1 ]; then
        echo ""
        echo "Encrypted files detected. Please decrypt them first:"
        echo "  cd step-data/secrets"
        echo "  openssl enc -aes-256-cbc -d -pbkdf2 -in intermediate_ca_key.enc -out intermediate_ca_key"
        echo "  openssl enc -aes-256-cbc -d -pbkdf2 -in root_ca_key.enc -out root_ca_key"
        echo "  openssl enc -aes-256-cbc -d -pbkdf2 -in password.enc -out password"
        echo ""
        exit 1
    fi
}

# Check if already initialized
if [ -f "$CONFIG_FILE" ]; then
    echo "Step CA already initialized"
    check_encrypted_files
else
    # Check if there are encrypted files without config (partial state)
    if [ -f "$SECRETS_DIR/intermediate_ca_key.enc" ] || [ -f "$SECRETS_DIR/root_ca_key.enc" ]; then
        echo "Encrypted secrets found but CA not configured."
        check_encrypted_files
    fi

    echo "Initializing Step CA..."

    # Create directories
    mkdir -p "$STEP_PATH/secrets" "$STEP_PATH/config" "$STEP_PATH/certs" "$STEP_PATH/db"

    # Generate password if not exists
    if [ ! -f "$PASSWORD_FILE" ]; then
        echo "Generating random password..."
        tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 32 > "$PASSWORD_FILE"
        chmod 600 "$PASSWORD_FILE"
    fi

    # Initialize CA
    step ca init \
        --name="$CA_NAME" \
        --dns="$CA_DNS" \
        --address="$CA_ADDRESS" \
        --provisioner="$CA_PROVISIONER" \
        --password-file="$PASSWORD_FILE" \
        --provisioner-password-file="$PASSWORD_FILE"

    echo ""
    echo "=========================================="
    echo "Step CA initialized!"
    echo "Admin password saved in: $PASSWORD_FILE"
    echo "Password: $(cat $PASSWORD_FILE)"
    echo "=========================================="
    echo ""
fi

# Start Step CA
exec step-ca "$CONFIG_FILE" --password-file="$PASSWORD_FILE"
