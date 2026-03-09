# TachoSystem

> **System kontroli i rozliczania czasu pracy kierowców** – pełna analiza plików DDD z kart kierowcy i tachografów, dostosowany do pakietu mobilności i Rozporządzenia (WE) nr 561/2006.

---

## Wymagania systemowe

| Składnik         | Minimalna wersja |
|-----------------|-----------------|
| PHP             | 7.4             |
| MySQL / MariaDB | 5.7 / 10.3      |
| Rozszerzenia PHP | `pdo_mysql`, `mbstring`, `json`, `fileinfo` |
| Serwer HTTP     | Apache 2.4+ (mod_rewrite) |

---

## Szybki start – instalacja

1. **Wgraj projekt na serwer**
2. **Uruchom kreator:** `http://twoja-domena.pl/setup.php`
   - Krok 1: Wymagania systemowe
   - Krok 2: Konfiguracja bazy danych
   - Krok 3: Tworzenie tabel
   - Krok 4: Konto superadmin
   - Krok 5: Finalizacja
3. Utwórz plik `.env` z wygenerowanego `.env.example`
4. Zaloguj się pod `/login`
5. Dodaj firmę (`/companies`) i wygeneruj licencję (`/admin/licenses`)

---

## Instalacja FTP (Cyberfolks)

```bash
export FTP_HOST="ftp.twojadomena.pl"
export FTP_USER="login_ftp"
export FTP_PASSWORD="haslo_ftp"
export FTP_REMOTE="/public_html/"

php setup/ftp_deploy.php
```

---

## Struktura projektu

```
TachoSystem/
├── database/schema.sql      # Schemat MySQL (10 tabel)
├── public/
│   ├── index.php            # Router / punkt wejścia
│   ├── .htaccess
│   └── assets/css,js
├── setup/ftp_deploy.php     # Upload FTP
├── src/
│   ├── config/config.php
│   ├── Core/                # Auth, Database, License, Router
│   ├── Controllers/         # Auth, Dashboard, Driver, Vehicle, Analysis, Report, Company
│   ├── Models/              # Activity (violations), Company, Driver, TachoFile, User, Vehicle
│   ├── Parsers/DDDParser.php
│   └── Views/               # Bootstrap 5 templates
├── uploads/
└── setup.php                # Kreator instalacji
```

---

## Kluczowe funkcjonalności

### Multi-tenant
Każda firma ma izolowane dane: kierowcy, pojazdy, pliki DDD, naruszenia.

### Parser DDD (binarny)
- Karty kierowcy (`.ddd`, `.c1b`) i tachografy VU (`.dt`)
- Gen1 (Rozp. 3821/85) i Gen2 (Rozp. 165/2014)
- 2-bajtowe rekordy aktywności: `slot | typ | minuty_od_północy`

### Analiza aktywności
- Widok **dzienny** – Gantt timeline (jazda/praca/dyspozycja/odpoczynek/przerwa)
- Widok **tygodniowy** – stacked bar chart (Chart.js)

### Naruszenia EU 561/2006

| Naruszenie | Limit | Art. | Grzywna |
|-----------|-------|------|---------|
| Dzienny czas jazdy | > 9h | Art. 6(1) | 200–500 zł |
| Ciągła jazda bez przerwy | > 4,5h | Art. 7 | 200–500 zł |
| Tygodniowy czas jazdy | > 56h | Art. 6(2) | 500–2000 zł |
| Dwutygodniowy czas jazdy | > 90h | Art. 6(3) | 500–2000 zł |
| Dzienny odpoczynek | < 9h | Art. 8(1) | 200–500 zł |
| Tygodniowy odpoczynek | < 45h | Art. 8(6) | 500–2000 zł |

### Raporty wydruku
- **Urlopówka** – ewidencja czasu pracy z podpisami
- **Delegacja** – rozliczenie krajów (Pakiet Mobilności 2020/1057/UE)

### System licencji SHA-256
```
Format: TACHO-XXXX-XXXX-XXXX-XXXX
Hash:   SHA256(company_id|key|modules|max_ops|max_drv|valid_to|hw_id|SECRET)
```
Moduły: `analysis`, `reports`, `violations`, `delegation`, `vacation`, `all`

---

## Bezpieczeństwo

- Hasła: bcrypt cost=12
- SQL: wyłącznie PDO prepared statements
- XSS: `htmlspecialchars()` wszędzie
- CSRF: tokeny w formularzach
- Sesje: HttpOnly, SameSite=Lax
- Licencje: `hash_equals()` (odporność na timing attacks)
- Izolacja: każde zapytanie zawiera scope `company_id`

---

## Konfiguracja (.env)

```env
APP_URL=https://twoja-domena.pl
DB_HOST=localhost
DB_NAME=tacho_system
DB_USER=tacho_user
DB_PASS=silne_haslo
LICENSE_SECRET=wygenerowany_podczas_instalacji_32_znaki
```

---

*TachoSystem v1.0.0 — Zgodny z prawem UE: Rozp. 561/2006, Rozp. 165/2014, Pakiet Mobilności 2020/1057/UE*
