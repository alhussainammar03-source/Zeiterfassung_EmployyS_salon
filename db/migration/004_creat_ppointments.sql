CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    employee_id INT NOT NULL,
    service_id INT NOT NULL,
    appointment_start DATETIME NOT NULL,
    appointment_end DATETIME NOT NULL,
    status ENUM(
        'angefragt',
        'bestaetigt',
        'abgeschlossen',
        'storniert'
    ) NOT NULL DEFAULT 'angefragt',
    customer_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (customer_id)
        REFERENCES `user`(id)
        ON DELETE CASCADE,

    FOREIGN KEY (employee_id)
        REFERENCES employees(id)
        ON DELETE RESTRICT,

    FOREIGN KEY (service_id)
        REFERENCES services(id)
        ON DELETE RESTRICT
);

INSERT INTO services
(name, description, duration_minutes, price)
VALUES
('Laser', 'Ganzkörper Laser', 60, 80.00),
('Gesichtsbehandlung', 'Professionelle Hautpflege', 45, 55.00),
('Massage', 'Entspannungsmassage', 60, 65.00);