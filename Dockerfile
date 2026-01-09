FROM php:8.2-apache

# Installer les dépendances nécessaires
RUN apt-get update && apt-get install -y \
    curl \
    tar \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Télécharger et installer step-cli (dernière version)
RUN STEP_VERSION=$(curl -s https://api.github.com/repos/smallstep/cli/releases/latest | grep '"tag_name"' | sed -E 's/.*"v([^"]+)".*/\1/') && \
    curl -L https://dl.smallstep.com/gh-release/cli/gh-release-header/v${STEP_VERSION}/step_linux_${STEP_VERSION}_amd64.tar.gz -o step.tar.gz && \
    tar -xzf step.tar.gz && \
    mv step_${STEP_VERSION}/bin/step /usr/local/bin/step && \
    rm -rf step.tar.gz step_${STEP_VERSION} && \
    chmod +x /usr/local/bin/step

# Vérifier l'installation
RUN step version

# Créer le répertoire pour les certificats
RUN mkdir -p /var/www/html/certs && \
    chown -R www-data:www-data /var/www/html/certs && \
    chmod 755 /var/www/html/certs

# Créer le répertoire pour step
RUN mkdir -p /root/.step && \
    chown -R www-data:www-data /root/.step

# Activer le module rewrite d'Apache
RUN a2enmod rewrite

# Exposer le port 80
EXPOSE 80

COPY init-step-ca.sh /tmp/init-step-ca.sh
RUN chmod 777 /tmp/init-step-ca.sh
RUN /tmp/init-step-ca.sh

# Démarrer Apache
CMD ["apache2-foreground"]
