#!/bin/bash
#
# Export Drupal configuration from production
# Run this on production server as root or www-data
#
# Usage: ./export-config.sh [site-name]
# Example: ./export-config.sh forseti

set -e

SITE_NAME=${1:-forseti}
SITE_ROOT="/var/www/html/${SITE_NAME}"
EXPORT_DIR="/tmp/config-export-${SITE_NAME}-$(date +%Y%m%d-%H%M%S)"
EXPORT_FILE="/tmp/${SITE_NAME}-config-$(date +%Y%m%d-%H%M%S).tar.gz"

echo "🔧 Drupal Config Export Tool"
echo "============================"
echo ""
echo "Site: ${SITE_NAME}"
echo "Root: ${SITE_ROOT}"
echo ""

# Validate site exists
if [ ! -d "${SITE_ROOT}" ]; then
  echo "❌ Error: Site root ${SITE_ROOT} does not exist"
  exit 1
fi

if [ ! -f "${SITE_ROOT}/vendor/bin/drush" ]; then
  echo "❌ Error: Drush not found at ${SITE_ROOT}/vendor/bin/drush"
  exit 1
fi

# Export configuration
echo "📦 Exporting configuration..."
cd "${SITE_ROOT}"
sudo -u www-data ./vendor/bin/drush config:export -y

# Verify export succeeded
if [ ! -d "${SITE_ROOT}/config/sync" ]; then
  echo "❌ Error: Config export failed - config/sync directory not found"
  exit 1
fi

FILE_COUNT=$(ls -1 "${SITE_ROOT}/config/sync" | wc -l)
echo "✅ Exported ${FILE_COUNT} configuration files"

# Create tarball
echo ""
echo "📦 Creating tarball..."
sudo tar -czf "${EXPORT_FILE}" -C "${SITE_ROOT}" config/sync/

TARBALL_SIZE=$(du -h "${EXPORT_FILE}" | cut -f1)
echo "✅ Created: ${EXPORT_FILE} (${TARBALL_SIZE})"

# Set permissions so it can be downloaded
sudo chmod 644 "${EXPORT_FILE}"

echo ""
echo "🎉 Export complete!"
echo ""
echo "To sync to development, run on your LOCAL machine:"
echo "  scp root@your-server:${EXPORT_FILE} /tmp/"
echo "  cd ~/forseti.life/sites/${SITE_NAME}"
echo "  tar -xzf /tmp/$(basename ${EXPORT_FILE})"
echo "  git add config/sync/"
echo "  git commit -m \"Sync config from production $(date +%Y-%m-%d)\""
echo "  git push"
echo ""
echo "Or use the sync-from-production.sh script from your dev machine."
