ALTER TABLE terminwunsche
    MODIFY COLUMN status ENUM(
        'angefragt',
        'bestaetigt',
        'abgelehnt',
        'abgeschlossen',
        'storniert'
    ) NOT NULL DEFAULT 'angefragt';

ALTER TABLE terminwunsche
    ADD COLUMN mitarbeiter_notiz TEXT NULL AFTER customer_note;