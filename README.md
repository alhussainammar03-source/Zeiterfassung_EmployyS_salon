# Bella Beauty – Buchungs- und Verwaltungssystem für Beautysalons

Eine Webanwendung in **purem PHP 8** (ohne Framework) mit **MySQL**, die den kompletten Ablauf eines Beautysalons abbildet: Online-Terminbuchung für Kundinnen, Terminverwaltung und Zeiterfassung für Mitarbeiterinnen sowie ein umfangreicher Adminbereich mit Auswertungen, Personalverwaltung und Treueprogramm.

Das Projekt ist bewusst **ohne Framework** umgesetzt, folgt aber einer klaren Schichtenarchitektur (Repository-, Service- und Validator-Layer) und legt Wert auf Sicherheit: PDO Prepared Statements, CSRF-Schutz, Passwort-Hashing, Rate-Limiting beim Login und Konfiguration über eine `.env`-Datei.

---

## Inhaltsverzeichnis

- [Rollen und Funktionen](#rollen-und-funktionen)
- [Sicherheit](#sicherheit)
- [Tech-Stack](#tech-stack)
- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Konfiguration (.env)](#konfiguration-env)
- [Cronjob für Terminerinnerungen](#cronjob-für-terminerinnerungen)
- [Projektstruktur](#projektstruktur)
- [Architektur](#architektur)
- [Datenbank](#datenbank)
- [Screenshots](#screenshots)
- [Bekannte Einschränkungen](#bekannte-einschränkungen)
- [Lizenz](#lizenz)

---

## Rollen und Funktionen

Das System kennt drei Rollen: **Kunde**, **Mitarbeiter** und **Admin**. Jede Seite ist über `Auth::requireRole()` geschützt.

### Kundinnen

- **Registrierung und Login** mit Passwort-Hashing und Passwort-vergessen-Funktion
- **Terminbuchung auf zwei Wegen**: entweder ein vom Salon vorgegebener Zeitslot oder ein freier Terminwunsch mit Wunschdatum und -uhrzeit
- **Auslastungsanzeige** pro Zeitslot (frei / in Bearbeitung / ausgebucht)
- Auswahl von **Dienstleistung und Mitarbeiterin**; die Terminlänge ergibt sich automatisch aus der Dauer der Leistung
- **Meine Termine**: bevorstehende, vergangene und stornierte Termine getrennt, nächster Termin hervorgehoben
- **Stornierung** zukünftiger Termine (mit Besitzprüfung und automatischer Benachrichtigung des Salons)
- **Treueprogramm**: Punktestand, Fortschrittsbalken und Einlösung einer Prämie als Gutscheincode
- **Profil** mit Kontaktdaten und Profilfoto-Upload
- Kunden-Dashboard mit nächstem Termin, Treuepunkten, News und aktuellen Aktionen

### Mitarbeiterinnen

- **Stempeluhr**: Ein- und Ausstempeln mit Ist-/Soll-Stunden-Vergleich (täglich, wöchentlich, monatlich)
- **Terminkalender** in Tages-, Wochen- und Monatsansicht, inklusive interner Notiz zum Termin
- **Urlaubsanträge** mit automatischer Werktagsberechnung, Überschneidungsprüfung und Jahreskontingent
- **Krankmeldung** inklusive Upload der Arbeitsunfähigkeitsbescheinigung und E-Mail-Benachrichtigung an den Admin
- Mitarbeiter-Dashboard mit heutiger Schicht, Terminen des Tages und offenen Anfragen

### Admin

- **Personalverwaltung**: Mitarbeiterinnen anlegen, bearbeiten, löschen, Status setzen, Foto hinterlegen
- **Kundenverwaltung**: Kundinnen bearbeiten, aktivieren/deaktivieren, löschen
- **Dienstleistungen** verwalten (Name, Kategorie, Beschreibung, Dauer, Preis, Bild)
- **Zeitslot-Verwaltung**: einzelne Slots, ganze Tage oder komplette Wochen auf einmal anlegen, bearbeiten und stornieren
- **Terminwünsche** bearbeiten: Liste mit Suche, Status- und Datumsfilter; Bestätigen, Ablehnen oder Abschließen mit automatischer E-Mail an die Kundin
- **Urlaubsanträge** genehmigen oder ablehnen
- **Krankmeldungen** einsehen (mit Zähler für ungelesene Meldungen)
- **Treueprogramm** konfigurieren (Punkte pro Euro, Schwelle, Prämientext) und Einlösungen verwalten
- **News und Aktionen** pflegen (Entwurf/veröffentlicht, Bild, Gültigkeitsdatum)
- **Auswertungen** mit Zeitraumfilter: Gesamtumsatz, Umsatzziel und Zielerreichung, Vergleich zur Vorperiode, Umsatzverlauf als Diagramm, meistgebuchte Leistungen und gebuchte Stunden je Mitarbeiterin
- **Zeiterfassungs-Statistik**: Ist-Stunden je Mitarbeiterin, aktuell eingestempelte Personen, Soll-/Ist-Differenz
- **CSV-Export** für Mitarbeiter, Kunden, Berichte und Zeiterfassung (Semikolon-getrennt mit UTF-8-BOM, direkt in Excel lesbar)

### Öffentlicher Bereich

Startseite, Leistungsübersicht (nach Kategorie gruppiert, mit „Beliebteste“-Badges), News-Seite, Über-uns-Seite und ein optionales **Tawk.to-Live-Chat-Widget**.

---

## Sicherheit

| Maßnahme | Umsetzung |
| --- | --- |
| SQL-Injection | Durchgängig PDO Prepared Statements, `EMULATE_PREPARES = false` |
| Passwörter | `password_hash()` mit `PASSWORD_DEFAULT`, Mindestlänge 8 Zeichen |
| CSRF | Eigene `Csrf`-Klasse, Token per `hash_equals()` geprüft, in allen POST-Formularen |
| Brute Force | `LoginRateLimiter`: Sperre nach 5 Fehlversuchen pro E-Mail **und** IP innerhalb von 15 Minuten |
| Passwort-Reset | Zufallstoken (`random_bytes(32)`), nur der SHA-256-Hash liegt in der DB, Gültigkeit 1 Stunde |
| User-Enumeration | „Passwort vergessen“ liefert immer dieselbe Rückmeldung, unabhängig davon, ob die E-Mail existiert |
| Zugriffsschutz | Rollenprüfung auf jeder Seite, zusätzlich Besitzprüfung beim Stornieren von Terminen |
| Geheimnisse | Alle Zugangsdaten in `.env`, per `.gitignore` vom Repository ausgeschlossen |
| XSS | Ausgaben werden mit `htmlspecialchars()` maskiert |

---

## Tech-Stack

| Bereich | Technologie |
| --- | --- |
| Sprache | PHP 8.1+ (`strict_types`, readonly Properties, Constructor Property Promotion, `match`) |
| Datenbank | MySQL / MariaDB über PDO |
| E-Mail | PHPMailer (SMTP) |
| Datei-Uploads | Cloudinary PHP SDK |
| Diagramme | Chart.js 4 (via CDN) |
| Frontend | Handgeschriebenes HTML/CSS (ca. 45 modulare CSS-Dateien), Vanilla JavaScript |
| Live-Chat | Tawk.to (optional) |
| Abhängigkeiten | Composer |

Bewusst **kein** Framework, kein Build-Step und kein CSS-Framework – das gesamte Layout ist selbst geschrieben.

---

## Voraussetzungen

- PHP **8.1 oder höher** (mit den Extensions `pdo_mysql`, `mbstring`, `curl`, `openssl`)
- MySQL 5.7+ oder MariaDB 10.4+
- Composer
- Lokal empfohlen: **XAMPP** (das Projekt ist für einen Ablageort unter `htdocs` ausgelegt)
- Ein SMTP-Konto für den Mailversand und ein kostenloser Cloudinary-Account für Bild-Uploads

---

## Installation

```bash
# 1. Repository in das Webroot klonen (bei XAMPP z. B. C:\xampp\htdocs)
git clone <repository-url> Bella_Project_V_2
cd Bella_Project_V_2

# 2. Abhängigkeiten installieren
composer install

# 3. Konfigurationsdatei anlegen
copy .env.example .env      # Windows
# cp .env.example .env      # macOS/Linux
```

**4. Datenbank einrichten**

Eine leere Datenbank anlegen (z. B. `bella_beauty`) und die SQL-Dateien aus `db/migration/` in numerischer Reihenfolge ausführen:

```bash
mysql -u root -p bella_beauty < db/migration/003_creat_servic.sql
mysql -u root -p bella_beauty < db/migration/004_creat_ppointments.sql
# ... usw. in aufsteigender Reihenfolge
```

**5. `.env` mit den eigenen Zugangsdaten füllen** (siehe nächster Abschnitt).

**6. Aufrufen** unter `http://localhost/Bella_Project_V_2/pages/home_page.php`

---

## Konfiguration (.env)

Alle Einstellungen werden über die `.env` im Projektwurzelverzeichnis geladen (eigener Loader in `includes/Env.php`, keine externe Bibliothek nötig). Die Datei darf **niemals** ins Repository committet werden.

```dotenv
# --- Datenbank ---
DB_HOST=localhost
DB_DATABASE=bella_beauty
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4

# --- Cloudinary (Foto-Upload) ---
CLOUDINARY_CLOUD_NAME=
CLOUDINARY_API_KEY=
CLOUDINARY_API_SECRET=
CLOUDINARY_UPLOAD_FOLDER=bella_beauty

# --- E-Mail (SMTP) ---
MAIL_SMTP_HOST=smtp.gmail.com
MAIL_SMTP_PORT=587
MAIL_SMTP_USERNAME=
MAIL_SMTP_PASSWORD=
MAIL_FROM_EMAIL=
MAIL_FROM_NAME=Bella Beauty
MAIL_ADMIN_EMAIL=

# --- Anwendung ---
# URL-Pfad des Projekts, z. B. /Bella_Project_V_2
APP_BASE_URL=/Bella_Project_V_2

# --- Tawk.to Live-Chat (optional) ---
TAWK_PROPERTY_ID=
TAWK_WIDGET_ID=
```

> Bei Gmail wird ein **App-Passwort** benötigt, nicht das normale Kontopasswort.
> Bleiben `TAWK_PROPERTY_ID` und `TAWK_WIDGET_ID` leer, wird das Chat-Widget einfach nicht geladen.

---

## Cronjob für Terminerinnerungen

Das Skript `cron/send_erinnerungen.php` verschickt automatisch Erinnerungsmails **2 Tage** und **1 Tag** vor einem bestätigten Termin. Bereits versendete Erinnerungen werden in der Datenbank markiert, sodass keine Mail doppelt verschickt wird. Alle Läufe werden in `cron/erinnerungen.log` protokolliert.

Einmal täglich ausführen:

```bash
# Linux (crontab -e) – täglich um 08:00 Uhr
0 8 * * * /usr/bin/php /pfad/zum/projekt/cron/send_erinnerungen.php

# Windows: Aufgabenplanung → tägliche Aufgabe
php C:\xampp\htdocs\Bella_Project_V_2\cron\send_erinnerungen.php
```

---

## Projektstruktur

```
Bella_Project_V_2/
├── admin/            # Adminbereich: Personal, Kunden, Services, News,
│                     # Aktionen, Urlaub, Krankmeldungen, Berichte, CSV-Exporte
├── assets/           # Bilder und Icons
├── config/           # Datenbank-, Mail- und Cloudinary-Konfiguration
├── cron/             # CLI-Skript für automatische Terminerinnerungen
├── customer/         # Kundenbereich: Buchung, Termine, Treueprogramm, Profil
├── db/
│   ├── migration/    # SQL-Migrationen (nummeriert)
│   └── seed.sql      # Beispieldaten
├── employee/         # Mitarbeiterbereich: Zeiterfassung, Urlaub, Krankmeldung
├── includes/         # Auth, CSRF, Env-Loader, Rate-Limiter, Header/Footer/Navbar
├── js/               # Vanilla JS (mobile Navigation)
├── models/           # Value Objects (z. B. Employee)
├── pages/            # Öffentliche Seiten, Login/Registrierung, Dashboards
├── repositories/     # Datenbankzugriff, eine Klasse pro Fachbereich
├── services/         # E-Mail-Versand, Cloudinary-Upload, Passwort-Reset
├── style/            # Modulare CSS-Dateien (eine je Seite/Komponente)
├── termin/           # Zeitslot- und Terminwunsch-Verwaltung (Admin)
├── validators/       # Formularvalidierung
├── .env              # Lokale Konfiguration (nicht im Repository)
└── composer.json
```

---

## Architektur

Die Anwendung ist in klare Schichten getrennt:

```
Seite (PHP/HTML)
      │  verwendet
      ▼
Repository  ──►  PDO (Prepared Statements)  ──►  MySQL
      │
      ├──►  Service   (EmailService, CloudinaryUploader, PasswordResetService)
      └──►  Validator (Eingabeprüfung)
```

- **Repositories** kapseln sämtliche SQL-Abfragen; die Seiten enthalten kein direktes SQL. Insgesamt 13 Repositories, je eines pro Fachbereich (Kunden, Mitarbeiter, Dienstleistungen, Terminwünsche, Zeitslots, Treueprogramm, News, Aktionen, Urlaub, Krankmeldungen, Zeiterfassung, Arbeitszeiten, Berichte).
- **Services** bündeln externe Systeme und wiederverwendbare Logik.
- **Validators** prüfen Formulareingaben zentral, statt in jeder Seite erneut.
- **Database** ist als Singleton implementiert und liefert eine konfigurierte PDO-Verbindung.
- Der **Env-Loader** ist selbst geschrieben und kommt ohne zusätzliche Bibliothek aus.

---

## Datenbank

Zentrale Tabellen:

| Tabelle | Zweck |
| --- | --- |
| `user` | Kundenkonten |
| `employees` | Mitarbeiter- und Adminkonten |
| `services` | Dienstleistungen mit Dauer und Preis |
| `zeitslots` | Vom Salon vorgegebene Termin-Zeitfenster |
| `terminwunsche` | Terminbuchungen und -anfragen inkl. Status und Notizen |
| `arbeitszeiten` | Wöchentlicher Schichtplan je Mitarbeiterin |
| `zeiterfassung` | Ein-/Ausstempel-Einträge |
| `urlaubsantraege` | Urlaubsanträge mit Status |
| `krankmeldungen` | Krankmeldungen inkl. AU-Nachweis |
| `loyalty_settings`, `loyalty_redemptions` | Treueprogramm |
| `salon_news`, `promotions` | News und Aktionen |
| `report_settings` | Umsatzziele |
| `login_versuche` | Protokoll für das Login-Rate-Limiting |

**Terminstatus:** `angefragt` → `bestaetigt` → `abgeschlossen`, alternativ `abgelehnt` oder `storniert`.
Beim Wechsel auf `abgeschlossen` werden automatisch Treuepunkte anhand des Leistungspreises gutgeschrieben.

---

## Screenshots

> Screenshots im Ordner `screenshots/` ablegen:

| Startseite | Terminbuchung |
| --- | --- |
| ![Startseite](screenshots/home.png) | ![Buchung](screenshots/booking.png) |

| Admin-Berichte | Zeiterfassung |
| --- | --- |
| ![Berichte](screenshots/reports.png) | ![Zeiterfassung](screenshots/time-tracking.png) |

---

## Bekannte Einschränkungen

- Für einige Tabellen (u. a. `arbeitszeiten`, `zeiterfassung`, `urlaubsantraege`, `krankmeldungen`, `loyalty_settings`, `salon_news`, `promotions`, `login_versuche`) existieren derzeit **keine Migrationsdateien**; sie müssen beim Aufsetzen einer frischen Datenbank manuell angelegt werden.
- Es gibt **keine Online-Zahlungsfunktion** – Zahlungen erfolgen vor Ort im Salon.
- Der **wöchentliche Schichtplan** wird bisher nur gelesen, eine Oberfläche zum Pflegen fehlt noch.
- Die Anwendung ist auf **Deutsch** ausgelegt; eine Mehrsprachigkeit ist nicht vorgesehen.
- Im Produktivbetrieb sollte die Anwendung ausschließlich über **HTTPS** laufen.

---

## Lizenz

Dieses Projekt entstand im Rahmen einer Weiterbildung und dient zu Lern- und Demonstrationszwecken.
