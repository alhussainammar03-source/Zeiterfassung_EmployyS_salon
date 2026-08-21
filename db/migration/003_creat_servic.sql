CREATE TABLE services (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    description TEXT,

    duration_minutes INT NOT NULL,

    price DECIMAL(10,2) NOT NULL,

    status ENUM('aktiv','inaktiv')
        DEFAULT 'aktiv',

    created_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB;