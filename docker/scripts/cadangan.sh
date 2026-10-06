#!/bin/sh
# Cadangan harian basis data SQLite (salinan konsisten lewat `.backup`), disimpan 30 hari. Dijalankan layanan `cadangan`.
set -e
DB="${DB_DATABASE:-/data/database.sqlite}"
DIR="${CADANGAN_DIR:-/cadangan}"
HARI="${CADANGAN_HARI:-30}"
JAM="${CADANGAN_JAM:-02:00}"

cadang() {
    mkdir -p "$DIR"
    BERKAS="$DIR/siman-$(date +%Y%m%d-%H%M%S).sqlite"
    sqlite3 "$DB" ".backup '$BERKAS'"
    sqlite3 "$BERKAS" "PRAGMA integrity_check;" | grep -qx ok
    gzip -f "$BERKAS"
    find "$DIR" -name 'siman-*.sqlite.gz' -mtime +"$HARI" -delete
    echo "$(date -Iseconds) cadangan selesai: $BERKAS.gz"
}

if [ "$1" = "sekali" ]; then
    cadang
    exit 0
fi

while true; do
    SEKARANG=$(date +%s)
    TARGET=$(date -d "$(date +%Y-%m-%d) $JAM" +%s 2>/dev/null || date +%s)
    [ "$TARGET" -le "$SEKARANG" ] && TARGET=$((TARGET + 86400))
    sleep $((TARGET - SEKARANG))
    cadang || echo "$(date -Iseconds) CADANGAN GAGAL" >&2
done
