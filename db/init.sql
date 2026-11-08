-- Kwoty przechowywane w groszach, udziały w setnych częściach procenta (10000 = 100%).
-- Zaimportuj ten plik do pustej bazy (np. w phpMyAdmin: Import).

CREATE TABLE `groups` (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE persons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NULL,
    share_bp INT UNSIGNED NULL COMMENT 'NULL = równa część pozostałego udziału',
    access_token CHAR(32) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_persons_token (access_token),
    CONSTRAINT fk_persons_group FOREIGN KEY (group_id) REFERENCES `groups` (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Osoby, dla których kupowane są prezenty (nie biorą udziału w rozliczeniu).
CREATE TABLE recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recipients_group FOREIGN KEY (group_id) REFERENCES `groups` (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    person_id INT UNSIGNED NOT NULL,
    recipient_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_purchases_person FOREIGN KEY (person_id) REFERENCES persons (id),
    CONSTRAINT fk_purchases_recipient FOREIGN KEY (recipient_id) REFERENCES recipients (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zwroty: from_person_id oddaje kwotę to_person_id. Zwrot zgłoszony przez dłużnika jest niepotwierdzony
-- (confirmed = 0) do czasu, aż odbiorca potwierdzi odbiór. Do rozliczenia liczą się tylko potwierdzone.
CREATE TABLE repayments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_person_id INT UNSIGNED NOT NULL,
    to_person_id INT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    confirmed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_repayments_from FOREIGN KEY (from_person_id) REFERENCES persons (id),
    CONSTRAINT fk_repayments_to FOREIGN KEY (to_person_id) REFERENCES persons (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
