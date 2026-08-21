ALTER TABLE terminwunsche
    ADD COLUMN erinnerung_2_tage_gesendet TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN erinnerung_1_tag_gesendet TINYINT(1) NOT NULL DEFAULT 0;