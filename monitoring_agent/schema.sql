-- =====================================================================
-- SentinelProc — database bootstrap for the Python monitoring agent.
--
-- OWNERSHIP CONTRACT
--   Laravel is the authoritative owner of this database. This file only
--   bootstraps the tables the Python agent reads and writes, so the agent
--   can be run on a machine before `php artisan migrate` has ever run.
--
--   Every definition below mirrors the FINAL state produced by the Laravel
--   migrations in sentinel_proc-web/database/migrations:
--       monitoring_snapshots <- 000004 + 000005 (snapshot column) + 000008
--       processes            <- 000004
--       alerts               <- 000008 (rebuilt; NOT the legacy shape)
--       processes_seen       <- 000005
--       activity_logs        <- 000009
--       process_lists        <- 000010
--
--   If you change a migration, change this file to match. The previous
--   drift here (a legacy `alerts` shape and a monitoring_snapshots table
--   missing snapshot_timestamp / process_count / status) is exactly what
--   migration 000008 had to work around.
--
--   Laravel-owned tables (users, cache, jobs, sessions, migrations,
--   system_audit_logs) are intentionally NOT created here — they belong to
--   `php artisan migrate`.
--
--   Do NOT add database credentials to this file.
--
--   All statements are IF NOT EXISTS, so running this against an already
--   migrated database is a no-op.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS sentinel_proc
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sentinel_proc;

-- ---------------------------------------------------------------------
-- processes_seen — SHA-256 first-seen tracking + VirusTotal cache
-- written by: record_first_seen(), is_hash_known(), update_vt_cache()
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS processes_seen (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_hash         VARCHAR(128)    NOT NULL,
    process_name      VARCHAR(255)    NULL,
    file_path         VARCHAR(500)    NULL,
    vt_checked_at     TIMESTAMP       NULL DEFAULT NULL,
    vt_malicious_count INT            NULL,
    vt_total_engines  INT             NULL,
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY processes_seen_file_hash_unique (file_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- monitoring_snapshots — one row per agent scan
-- written by: insert_snapshot() -> (snapshot, snapshot_timestamp,
--             process_count, status)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS monitoring_snapshots (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    snapshot           JSON            NULL,
    snapshot_timestamp TIMESTAMP       NOT NULL,
    process_count      INT             NULL,
    cpu_usage          DECIMAL(5,2)    NULL,
    memory_usage       DECIMAL(8,2)    NULL,
    disk_usage         DECIMAL(8,2)    NULL,
    processes          JSON            NULL,
    alerts             JSON            NULL,
    risk_score         JSON            NULL,
    status             VARCHAR(255)    NOT NULL DEFAULT 'normal',
    created_at         TIMESTAMP       NULL DEFAULT NULL,
    updated_at         TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY monitoring_snapshots_snapshot_timestamp_index (snapshot_timestamp),
    KEY monitoring_snapshots_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- processes — one row per process per scan
-- written by: insert_process() -> (monitoring_snapshot_id, pid, name,
--             path, cpu_percent, memory_mb, status, hash, first_seen,
--             risk_level, virus_total_data)
-- `status` holds the OS username; risk_level is 'low'|'medium'|'high'
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS processes (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    monitoring_snapshot_id BIGINT UNSIGNED NOT NULL,
    pid                    INT             NOT NULL,
    name                   VARCHAR(255)    NOT NULL,
    path                   VARCHAR(512)    NULL,
    cpu_percent            DECIMAL(5,2)    NULL,
    memory_mb              DECIMAL(10,2)   NULL,
    status                 VARCHAR(50)     NULL,
    hash                   VARCHAR(64)     NULL,
    first_seen             INT             NULL,
    risk_level             VARCHAR(20)     NOT NULL DEFAULT 'low',
    virus_total_data       JSON            NULL,
    created_at             TIMESTAMP       NULL DEFAULT NULL,
    updated_at             TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY processes_pid_index (pid),
    KEY processes_hash_index (hash),
    CONSTRAINT processes_monitoring_snapshot_id_foreign
        FOREIGN KEY (monitoring_snapshot_id)
        REFERENCES monitoring_snapshots (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- alerts — one row per medium/high risk process
-- written by: insert_alert() -> (monitoring_snapshot_id, process_id,
--             alert_type, severity, message, details, acknowledged)
-- No foreign keys, matching migration 000008: monitoring_snapshot_id is
-- a plain INT reference (it is not the bigint the other tables use) and
-- process_id is nullable on purpose.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alerts (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    monitoring_snapshot_id INT             NOT NULL,
    process_id             BIGINT UNSIGNED NULL,
    alert_type             VARCHAR(100)    NOT NULL,
    severity               VARCHAR(20)     NOT NULL,
    message                TEXT            NOT NULL,
    details                JSON            NULL,
    acknowledged           TINYINT(1)      NOT NULL DEFAULT 0,
    created_at             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY alerts_monitoring_snapshot_id_index (monitoring_snapshot_id),
    KEY alerts_process_id_index (process_id),
    KEY alerts_alert_type_index (alert_type),
    KEY alerts_acknowledged_index (acknowledged)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- activity_logs — audit trail of every detected process
-- written by: insert_activity_log() -> (monitoring_snapshot_id,
--             process_name, pid, event_type, risk_level, risk_score,
--             path, hash, details)
-- event_type: detected | risk_change | first_seen | vt_flagged
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    monitoring_snapshot_id INT             NULL,
    process_name           VARCHAR(255)    NOT NULL,
    pid                    INT             NULL,
    event_type             VARCHAR(50)     NOT NULL,
    risk_level             VARCHAR(20)     NULL,
    risk_score             INT             NULL,
    path                   VARCHAR(512)    NULL,
    hash                   VARCHAR(64)     NULL,
    details                JSON            NULL,
    created_at             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY activity_logs_monitoring_snapshot_id_index (monitoring_snapshot_id),
    KEY activity_logs_process_name_index (process_name),
    KEY activity_logs_event_type_index (event_type),
    KEY activity_logs_created_at_index (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- process_lists — whitelist / blacklist rules
-- read by: load_process_lists() -> SELECT type, match_by, value
-- `value` is a MySQL keyword, so it stays backticked.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS process_lists (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type         VARCHAR(20)     NOT NULL,
    match_by     VARCHAR(20)     NOT NULL,
    `value`      VARCHAR(512)    NOT NULL,
    process_name VARCHAR(255)    NULL,
    reason       TEXT            NULL,
    added_by     BIGINT UNSIGNED NULL,
    created_at   TIMESTAMP       NULL DEFAULT NULL,
    updated_at   TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY process_lists_type_index (type),
    UNIQUE KEY process_lists_type_match_by_value_unique (type, match_by, `value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
