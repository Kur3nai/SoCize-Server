CREATE DATABASE socize_filestorage;
USE socize_filestorage;

CREATE TABLE user_role(
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL
);

CREATE TABLE user(
    username VARCHAR(100) PRIMARY KEY,
    role_id INT NOT NULL,
    user_password VARCHAR(200) NOT NULL,  
    phone_number VARCHAR(15) NOT NULL,    
    email VARCHAR(100) NOT NULL,
    FOREIGN KEY (role_id) REFERENCES user_role(role_id) 
);


CREATE TABLE file( 
    filename VARCHAR(50) NOT NULL,
    username VARCHAR(100) NOT NULL,
    file_directory VARCHAR(255) NOT NULL,
    upload_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (username, filename), 
    FOREIGN KEY (username) REFERENCES user(username)
);

INSERT INTO user_role (role_name)
VALUES
("user"),
("admin");

INSERT INTO user (username, role_id, user_password, phone_number, email)
VALUES
("AdamZ", 2, "$2a$10$eomAxPlTOl2RWJtPa3AxPOnRLJkKBAmCLzVd.7rZ.TGHtUnMdMwQ6", "123412341234", "Adam@gmail.com"),
("Yen_Tze", 2, "$2a$10$e5fihghogas7oSKsZJU1quTdNjZskNHsBRpAhwKsAD5kF/5WNUZxq", "356354635463", "YT@gmail.com"),
("Bombaclat", 2, "$2a$10$ysIejIqwkVfnTteA3kTEoeSVtqWkkMenc9D5g22L08l3m5embmFSu", "1723528453", "Bombablat@gmail.com");


DELIMITER $$
CREATE PROCEDURE get_user_details(IN username_ VARCHAR(100))
BEGIN
    SELECT u.username, u.user_password, r.role_name 
    FROM user u
    JOIN user_role r ON u.role_id = r.role_id
    WHERE u.username = username_;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE create_user_account(IN username_ VARCHAR(100),IN password_ VARCHAR(200),IN email_ VARCHAR(100),IN phone_ VARCHAR(15))
BEGIN
    INSERT INTO user (username, user_password, email, phone_number, role_id)
    VALUES (username_, password_, email_, phone_, 1);
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE delete_user_account(IN username_ VARCHAR(100))
BEGIN   
    DELETE FROM user 
    WHERE username = username_ AND role_id != 2;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE add_file_record(IN filename_ VARCHAR(50),IN username_ VARCHAR(100),IN directory_ VARCHAR(255)
)
BEGIN
    INSERT INTO file (filename, username, file_directory)
    VALUES (filename_, username_, directory_); 
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE delete_file_record(IN username_ VARCHAR(100),IN filename_ VARCHAR(50)
)
BEGIN   
    DELETE FROM file 
    WHERE username = username_ AND filename = filename_;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE get_user_files(IN username_ VARCHAR(100))
BEGIN   
    SELECT filename, file_directory, upload_time 
    FROM file 
    WHERE username = username_
    ORDER BY upload_time DESC;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE get_all_usernames()
BEGIN
    SELECT username FROM user
    WHERE role_id != 2
    ORDER BY username ASC;
END $$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE get_verified_file_path(IN username_ VARCHAR(100), IN filename_ VARCHAR(50))
BEGIN   
    SELECT file_directory FROM file
    WHERE username = username_ AND filename = filename_;
END $$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE get_file_path(IN username_ VARCHAR(100), IN filename_ VARCHAR(50))
BEGIN   
    SELECT file_directory FROM file
    WHERE username = username_ AND filename = filename_;
END $$get_user_details
DELIMITER ;

CALL get_user_details ("AdamZ");


DELIMITER $$
CREATE PROCEDURE get_account_details(IN username_ VARCHAR(100))
BEGIN
    SELECT username, email, phone_number 
    FROM user
    WHERE username = username_ AND role_id != 2;
END $$
DELIMITER ;
