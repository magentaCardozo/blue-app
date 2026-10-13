# Utiliser Ubuntu 22.04 pour une meilleure compatibilité avec PHP 8.3
FROM ubuntu:22.04
ARG DEBIAN_FRONTEND=noninteractive

# Mettre à jour les paquets et installer les prérequis
RUN apt-get update && apt-get install -y software-properties-common \
    && add-apt-repository ppa:ondrej/php \
    && apt-get update

# Installer Apache et PHP 8.3 avec les extensions essentielles
RUN apt-get install -y \
    apache2 \
    libapache2-mod-php8.3 \
    php8.3-mysql \
    php8.3-zip \
    php8.3-gd \
    php8.3-mbstring \
    php8.3-curl \
    php8.3-intl \
    php8.3-bcmath \
    php8.3-opcache \
    php8.3-soap \
    php8.3-xml \
    php8.3-pgsql \
    git \
    unzip \
    curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Limites PHP (6 champs photo x 4 images par contrôle = 24 fichiers par envoi)
RUN printf "memory_limit = 512M\nmax_execution_time = 120\nupload_max_filesize = 64M\npost_max_size = 64M\nmax_file_uploads = 50\n" \
    > /etc/php/8.3/apache2/conf.d/99-blue-app.ini

# Activer mod_rewrite pour Apache
RUN a2enmod rewrite

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copier les fichiers du projet dans le répertoire de travail d'Apache
COPY . /var/www/html/

# Définir le répertoire de travail
WORKDIR /var/www/html

# Installer les dépendances du projet
RUN composer install --no-dev --optimize-autoloader

# Donner les permissions appropriées aux fichiers et répertoires
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exposer le port 80
EXPOSE 80

# Lancer Apache en mode foreground
CMD ["apachectl", "-D", "FOREGROUND"]
