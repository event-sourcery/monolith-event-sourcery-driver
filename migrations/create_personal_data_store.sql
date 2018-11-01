CREATE TABLE personal_data_store
(
    id int PRIMARY KEY AUTO_INCREMENT,
    personal_key varchar(36) NOT NULL,
    data_key varchar(36) NOT NULL,
    encrypted_personal_data text,
    encryption varchar(255) NOT NULL,
    stored_at datetime,
    cleared_at datetime
);
