CREATE TABLE events (
    event_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name         VARCHAR(255) NOT NULL,
    event_info         TEXT,
    icon_url           VARCHAR(512),
    chat_url           VARCHAR(512) NOT NULL UNIQUE,
    start_date         DATE,
    end_date           DATE,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
