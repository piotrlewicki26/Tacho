# TachoSystem – Generator Licencji

> Osobny, samodzielny system do generowania i zarządzania licencjami dla aplikacji TachoSystem.

---

## Spis treści

1. [Opis](#opis)
2. [Wymagania](#wymagania)
3. [Instalacja](#instalacja)
4. [Konfiguracja](#konfiguracja)
5. [Użytkowanie](#użytkowanie)
6. [Format klucza licencji](#format-klucza-licencji)
7. [Struktura projektu](#struktura-projektu)
8. [Bezpieczeństwo](#bezpieczeństwo)

---

## Opis

Generator Licencji to **niezależna aplikacja webowa** napisana w PHP, przeznaczona do wystawiania
i zarządzania kluczami licencyjnymi dla systemu TachoSystem (system kontroli i rozliczania czasu
pracy kierowców).

### Funkcjonalności

| Funkcja | Opis |
|---------|------|
| **Generowanie licencji** | Tworzenie podpisanych kluczy TACHO-XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX |
| **Wybór modułów** | Analiza, Raporty, Naruszenia, Delegacje, Urlopy lub wszystkie |
| **Limity** | Konfigurowalny max. liczby operatorów i kierowców |
| **Ważność** | Definiowanie okresu ważności (data od/do) |
| **Hardware binding** | Opcjonalne powiązanie licencji z konkretnym serwerem |
| **Weryfikacja** | Sprawdzanie integralności kryptograficznej (HMAC-SHA256) |
| **Zarządzanie** | Aktywacja / dezaktywacja / usuwanie licencji |
| **Zabezpieczony dostęp** | Logowanie z hasłem bcrypt |

---

## Wymagania

| Składnik | Minimalna wersja |
|----------|-----------------|
| PHP | 8.0 |
| Rozszerzenie PDO | ✓ |
| Rozszerzenie PDO SQLite | ✓ |
| Rozszerzenie mbstring | ✓ |
| Apache | 2.4+ z `mod_rewrite` |

> Aplikacja korzysta z **SQLite** jako bazy danych – nie jest wymagany zewnętrzny serwer MySQL.

---

## Instalacja

1. **Skopiuj pliki** na serwer Apache (katalog dostępny przez HTTP).

2. **Przejdź do kreatora instalacji:**
   ```
   http://twoja-domena/setup.php
   ```

3. W kreatorze:
   - Krok 1: Weryfikacja wymagań PHP.
   - Krok 2: Ustaw nazwę użytkownika / hasło administratora oraz **sekret licencji** (`LICENSE_SECRET`).

4. Po instalacji plik `.installed` zostaje utworzony automatycznie.
   `setup.php` zostaje zablokowany – usuń go z serwera produkcyjnego dla bezpieczeństwa.

---

## Konfiguracja

Plik `.env` (generowany przez setup.php lub tworzony ręcznie na podstawie `.env.example`):

```dotenv
DATABASE_PATH=/bezwzgledna/sciezka/do/database/licenses.db
LICENSE_SECRET=replace_with_strong_random_secret_min_32_characters
APP_DEBUG=false
```

> ⚠️ **`LICENSE_SECRET` musi być identyczny z kluczem skonfigurowanym w głównym systemie TachoSystem.**
> Zmiana sekretu uniemożliwi weryfikację już wystawionych licencji.

---

## Użytkowanie

### Generowanie licencji

1. Zaloguj się pod adresem `/login`.
2. Kliknij **„Generuj licencję"** w menu bocznym.
3. Wypełnij formularz:
   - ID firmy (unikalny identyfikator, np. `FIRMA01`)
   - Nazwa firmy
   - Dostępne moduły
   - Limity operatorów i kierowców
   - Okres ważności
   - (Opcjonalnie) ID sprzętu
4. Kliknij **„Generuj licencję"** – klucz zostanie wyświetlony i zapisany w bazie.

### Weryfikacja klucza

1. Przejdź do **„Weryfikuj klucz"**.
2. Podaj klucz licencji i ID firmy.
3. System sprawdza:
   - Istnienie klucza w bazie.
   - Zgodność ID firmy.
   - Aktywność licencji.
   - Datę ważności.
   - Integralność skrótu HMAC-SHA256.

---

## Format klucza licencji

```
TACHO-XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX
```

- 4 grupy po 8 wielkich liter szesnastkowych (łącznie 32 znaki losowe = 128 bitów entropii).
- Generowany za pomocą `random_bytes()` (CSPRNG).

### Algorytm podpisywania

```
hash_hmac(
    'sha256',
    "{company_id}|{license_key}|{modules}|{max_operators}|{max_drivers}|{valid_to}|{hardware_id}",
    LICENSE_SECRET
)
```

Skrót jest przechowywany w bazie i weryfikowany przy każdej operacji sprawdzania klucza
z użyciem `hash_equals()` (odporna na ataki czasowe).

---

## Struktura projektu

```
.
├── index.php               # Główny punkt wejścia / router
├── setup.php               # Kreator instalacji (jednorazowy)
├── config.php              # Ładowanie .env i stałe aplikacji
├── .htaccess               # Reguły przepisywania URL (Apache)
├── .env.example            # Szablon konfiguracji
├── .gitignore
├── README.md
│
├── src/
│   ├── Auth.php            # Uwierzytelnianie sesyjne (bcrypt)
│   ├── Database.php        # Wrapper PDO/SQLite + inicjalizacja schematu
│   └── LicenseManager.php  # Generowanie, weryfikacja i zarządzanie licencjami
│
├── views/
│   ├── layout.php          # Szablon Bootstrap 5 (sidebar + nagłówek)
│   ├── login.php           # Formularz logowania
│   ├── dashboard.php       # Lista licencji + statystyki
│   ├── generate.php        # Formularz generowania licencji
│   └── verify.php          # Weryfikacja klucza licencji
│
└── database/
    └── .gitkeep            # Katalog na plik SQLite (plik .db jest ignorowany przez git)
```

---

## Bezpieczeństwo

| Mechanizm | Implementacja |
|-----------|---------------|
| Uwierzytelnianie | bcrypt (cost=12) |
| Klucz licencji | CSPRNG `random_bytes()` – 128 bitów entropii |
| Podpis | HMAC-SHA256 z tajnym kluczem ≥ 32 znaków |
| Weryfikacja podpisu | `hash_equals()` – odporność na ataki czasowe |
| Sesje | `session_regenerate_id()`, HttpOnly, SameSite=Lax |
| Zapytania SQL | PDO Prepared Statements – ochrona przed SQL Injection |
| XSS | `htmlspecialchars()` wszędzie w widokach |
| Dostęp do bazy | Plik SQLite poza web-rootem (konfigurowalny) |
