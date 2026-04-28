#!/bin/bash
echo "--- Fixing MPM at Runtime ---"
rm -f /etc/apache2/mods-enabled/mpm_*
a2enmod mpm_prefork

echo "--- Configuring Port ---"
sed -i "s/80/${PORT:-80}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

echo "--- Starting Apache ---"
exec apache2-foreground
