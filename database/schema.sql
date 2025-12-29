CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(191) NOT NULL UNIQUE,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'approver', 'user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    must_reset TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE overtime_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    reason TEXT NOT NULL,
    work_type ENUM('office', 'field') NOT NULL DEFAULT 'office',
    status ENUM('pending', 'approved', 'denied') NOT NULL DEFAULT 'pending',
    denial_reason TEXT NULL,
    approver_id INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_requests_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_requests_approver FOREIGN KEY (approver_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(20) NOT NULL,
    event_at DATETIME NOT NULL,
    CONSTRAINT fk_events_request FOREIGN KEY (request_id) REFERENCES overtime_requests(id),
    CONSTRAINT fk_events_user FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_requests_status ON overtime_requests(status);
CREATE INDEX idx_requests_work_date ON overtime_requests(work_date);
