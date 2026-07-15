SET FOREIGN_KEY_CHECKS = 0;

-- TABLES

CREATE TABLE students (
    id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    full_name        VARCHAR(150)     NOT NULL,
    email            VARCHAR(255)     NOT NULL,
    password_hash    CHAR(60)         NOT NULL,
    photo_path       VARCHAR(255)              DEFAULT NULL,
    account_status   ENUM('active','locked','suspended','deleted')
                                      NOT NULL DEFAULT 'active',
    failed_logins    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until     DATETIME                  DEFAULT NULL,
    last_login_at    DATETIME                  DEFAULT NULL,
    last_login_ip    VARCHAR(45)               DEFAULT NULL,
    created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT pk_students      PRIMARY KEY (id),
    CONSTRAINT uq_student_email UNIQUE      (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE login_attempts (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id     INT UNSIGNED            DEFAULT NULL,
    email_tried    VARCHAR(255)    NOT NULL,
    ip_address     VARCHAR(45)     NOT NULL,
    user_agent     VARCHAR(512)            DEFAULT NULL,
    attempt_result ENUM('success','failure','locked')
                                   NOT NULL DEFAULT 'failure',
    attempted_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_login_attempts PRIMARY KEY (id),
    CONSTRAINT fk_la_student     FOREIGN KEY (student_id)
        REFERENCES students(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE sessions (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id     INT UNSIGNED    NOT NULL,
    session_token  CHAR(64)        NOT NULL,
    ip_address     VARCHAR(45)             DEFAULT NULL,
    user_agent     VARCHAR(512)            DEFAULT NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_active_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,
    expires_at     DATETIME        NOT NULL,
    is_valid       TINYINT(1)      NOT NULL DEFAULT 1,
    CONSTRAINT pk_sessions     PRIMARY KEY (id),
    CONSTRAINT uq_sess_token   UNIQUE      (session_token),
    CONSTRAINT fk_sess_student FOREIGN KEY (student_id)
        REFERENCES students(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id  INT UNSIGNED            DEFAULT NULL,
    action      ENUM(
                    'register',
                    'login_success',
                    'login_failure',
                    'logout',
                    'account_locked',
                    'account_unlocked',
                    'password_changed',
                    'account_deleted'
                )               NOT NULL,
    ip_address  VARCHAR(45)             DEFAULT NULL,
    old_value   LONGTEXT                DEFAULT NULL,
    new_value   LONGTEXT                DEFAULT NULL,
    occurred_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_audit_log  PRIMARY KEY (id),
    CONSTRAINT fk_al_student FOREIGN KEY (student_id)
        REFERENCES students(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- INDEXES
-- -------------------------------------------------------------------------

CREATE INDEX idx_la_email_time   ON login_attempts (email_tried, attempted_at);
CREATE INDEX idx_la_ip_time      ON login_attempts (ip_address,  attempted_at);
CREATE INDEX idx_al_student_time ON audit_log      (student_id,  occurred_at);
CREATE INDEX idx_al_action_time  ON audit_log      (action,      occurred_at);

SET FOREIGN_KEY_CHECKS = 1;
