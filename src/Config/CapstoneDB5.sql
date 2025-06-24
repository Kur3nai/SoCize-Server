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

--

DELIMITER $$
CREATE PROCEDURE get_user_credentials(IN username_ VARCHAR(100))
BEGIN
    SELECT user_password FROM user 
    WHERE username = username_;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE create_user_account(IN username_ VARCHAR(100),IN password_ VARCHAR(200),IN email_ VARCHAR(100),IN phone_ VARCHAR(15),IN roleid_ INT
)
BEGIN
    INSERT INTO user (username, user_password, email, phone_number, role_id)
    VALUES (username_, password_, email_, phone_, 1);
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE delete_user_account(IN username_ VARCHAR(100))
BEGIN   
    DELETE FROM user WHERE username = username_;
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

--DOWN HERE

DELIMITER $$
CREATE PROCEDURE get_all_users()
BEGIN
    SELECT username from USER
    ORDER BY username ASC;
END $$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE get_file_path(IN username_ VARCHAR(100), IN filename_ VARCHAR(50))
BEGIN   
    SELECT file_directory FROM file
    WHERE username = username_ AND filename = filename_
END $$
DELIMITER ;


DELIMITER $$
CREATE PROCEDURE get_account_details(IN username_ VARCHAR(100))
BEGIN
    sELECT username,email,user_password,phone_number,role_id FROM user
    WHERE username = username_
END $$
DELIMITER ;


