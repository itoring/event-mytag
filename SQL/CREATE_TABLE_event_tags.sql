CREATE TABLE event_tags (
    tag_id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id        INT UNSIGNED NOT NULL,
    tag_name           VARCHAR(255) NOT NULL,
    sort_order         INT UNSIGNED DEFAULT 0,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES event_tag_categories(category_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
