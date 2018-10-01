DROP TABLE IF EXISTS personal_cryptography_store;

CREATE TABLE personal_cryptography_store
(
    id int PRIMARY KEY AUTO_INCREMENT,
    personal_key varchar(36) NOT NULL,
    cryptographic_details text,
    encryption varchar(255),
    added_at datetime,
    cleared_at datetime
);

ALTER TABLE personal_cryptography_store COMMENT = 'event sourcery personal cryptography store';