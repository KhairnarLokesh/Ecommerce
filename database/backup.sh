#!/bin/bash
# =====================================================================
# Kalika Enterprises - Automated Database Backup Cron Script (Linux)
# Recommended Cron: 0 2 * * * /bin/bash /home/user/public_html/database/backup.sh
# =====================================================================

set -e

# Resolve base directories
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="${SCRIPT_DIR}/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/kalika_backup_${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

# Protect backup folder
if [ ! -f "${BACKUP_DIR}/.htaccess" ]; then
    echo "Require all denied" > "${BACKUP_DIR}/.htaccess"
fi

# Run PHP backup engine or mysqldump
if [ -f "${SCRIPT_DIR}/backup.php" ]; then
    php "${SCRIPT_DIR}/backup.php"
fi

# Rotate backups: Keep only the 14 most recent .sql or .sql.gz files
cd "$BACKUP_DIR"
ls -t kalika_backup_*.sql* 2>/dev/null | tail -n +15 | xargs -r rm -f

echo "Backup and rotation completed at $(date)"
