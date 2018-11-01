CREATE TABLE event_store
(
    id int PRIMARY KEY AUTO_INCREMENT,
    stream_id varchar(255) NOT NULL,
    stream_version int NOT NULL,
    event_name varchar(255) NOT NULL,
    event_data text,
    meta_data text,
    raised_at datetime
);
