#!/bin/bash
# =============================================================================
# WeAct MySQL Daily Backup Script
# - Runs via cron every night at 3:00 AM
# - Keeps last 7 days of backups (rotation)
# - Compressed with gzip
# =============================================================================
set -euo pipefail

# --- Configuration -----------------------------------------------------------
BACKUP_DIR="/var/backups/weact-mysql"
DB_NAME="weact"
# Identifiants MySQL lus dans un fichier d'options protégé (chmod 600), jamais dans ce script :
#   /root/.my.cnf
#   [mysqldump]
#   user=...
#   password=...
# Le script reste ainsi identique au dépôt (aucune modification locale qui bloquerait `git pull` au déploiement)
# et le mot de passe n'apparaît plus dans la liste des processus.
MYSQL_DEFAULTS_FILE="${MYSQL_DEFAULTS_FILE:-/root/.my.cnf}"
RETENTION_DAYS=7
DATE=$(date +%Y-%m-%d_%H%M)
FILENAME="${DB_NAME}_${DATE}.sql.gz"
LOG_FILE="/var/log/weact-backup.log"

# --- Setup -------------------------------------------------------------------
mkdir -p "$BACKUP_DIR"

log() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# --- Backup ------------------------------------------------------------------
log "Starting backup: $FILENAME"

if [ ! -r "$MYSQL_DEFAULTS_FILE" ]; then
  log "ERROR: fichier d'identifiants MySQL introuvable ou illisible : $MYSQL_DEFAULTS_FILE"
  exit 1
fi

if mysqldump \
  --defaults-extra-file="$MYSQL_DEFAULTS_FILE" \
  --single-transaction \
  --routines \
  --triggers \
  --quick \
  "$DB_NAME" | gzip > "$BACKUP_DIR/$FILENAME"; then

  SIZE=$(du -h "$BACKUP_DIR/$FILENAME" | cut -f1)
  log "Backup OK: $FILENAME ($SIZE)"
else
  log "ERROR: Backup failed!"
  exit 1
fi

# --- Rotation (delete backups older than RETENTION_DAYS) ---------------------
DELETED=$(find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -mtime +$RETENTION_DAYS -type f -print -delete | wc -l)
if [ "$DELETED" -gt 0 ]; then
  log "Rotation: deleted $DELETED old backup(s)"
fi

# --- Summary -----------------------------------------------------------------
TOTAL=$(find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -type f | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
log "Done. $TOTAL backup(s) on disk, total: $TOTAL_SIZE"
