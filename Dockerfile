# Production image for portal-api — self-contained FrankenPHP app (k8s / prod).
# Built on the shared FrankenPHP base (dailyapps/php:8.5). Prod deps installed
# fresh; secrets are injected at RUNTIME by the Vault Agent (never baked in).
FROM dailyapps/php:8.5
COPY --chown=dailyapps:dailyapps . /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress
# Inherits WORKDIR /var/www/html, the Caddyfile and `frankenphp run` CMD from the base.
