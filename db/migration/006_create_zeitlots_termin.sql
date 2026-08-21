-- Verfügbare Zeitslots (z. B. von dir als Anbieter vordefiniert)
CREATE TABLE IF NOT EXISTS zeitslots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum DATE NOT NULL,
    uhrzeit TIME NOT NULL,
    dauer_minuten INT NOT NULL DEFAULT 30,
    ist_gebucht TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY unique_slot (datum, uhrzeit)
) ENGINE=InnoDB;

-- Gebuchte Termine
CREATE TABLE IF NOT EXISTS termine (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zeitslot_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefon VARCHAR(30) DEFAULT NULL,
    nachricht TEXT DEFAULT NULL,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zeitslot_id) REFERENCES zeitslots(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Beispiel-Zeitslots für die nächsten Tage einfügen (optional, zum Testen)
-- Erzeugt Slots von 09:00 bis 16:30 in 30-Minuten-Schritten für 5 Werktage
INSERT INTO zeitslots (datum, uhrzeit, dauer_minuten) VALUES
('2026-07-16', '09:00:00', 30),
('2026-07-16', '09:30:00', 30),
('2026-07-16', '10:00:00', 30),
('2026-07-16', '10:30:00', 30),
('2026-07-16', '11:00:00', 30),
('2026-07-17', '09:00:00', 30),
('2026-07-17', '09:30:00', 30),
('2026-07-17', '10:00:00', 30),
('2026-07-17', '10:30:00', 30),
('2026-07-17', '11:00:00', 30);

