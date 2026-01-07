#!/bin/sh

if [ ! -f /home/step/config/ca.json ]; then
    echo "Initialisation de Step CA..."
    step ca init \
        --name="Ma CA Interne" \
        --dns="step-ca,localhost" \
        --address=":9000" \
        --provisioner="admin" \
        --password-file=/dev/stdin \
        --provisioner-password-file=/dev/stdin <<EOF
VotreMotDePasseSecurise
VotreMotDePasseSecurise
EOF
    echo "Step CA initialisé!"
else
    echo "Step CA déjà initialisé"
fi

exec step-ca /home/admin-ca/.step/config/ca.json
