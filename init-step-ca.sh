#!/bin/sh

if [ ! -f /home/step/config/ca.json ]; then
    echo "Initialisation de Step CA..."
    step ca init \
        --name="Vaultica CA Interne" \
        --dns="step-ca,localhost,10.10.231.3" \
        --address=":9000" \
        --provisioner="admin" \
        --password-file= \
        --provisioner-password-file
    echo "Step CA initialisé!"
else
    echo "Step CA déjà initialisé"
fi

exec step-ca /home/admin-ca/.step/config/ca.json