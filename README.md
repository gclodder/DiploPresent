# DiploPresent 3

Vue 3 + Vite + Tailwind frontend met een PHP JSON-API. Node.js is alleen nodig voor ontwikkeling en de productiebuild; de host serveert daarna statische bestanden en PHP.

## Lokaal ontwikkelen

```powershell
npm install
Copy-Item .env.example .env
npm run dev
```

`npm run dev` start zowel de PHP API op `127.0.0.1:8080` als Vite. Voor afzonderlijk starten:

```powershell
npm run dev:api
npm run dev:frontend
```

Wil je de devserver vanaf een andere laptop/telefoon in hetzelfde netwerk openen, gebruik dan:

```powershell
npm run dev:host
```

Open daarna `http://<ip-adres-van-deze-computer>:5173/`. De PHP API blijft lokaal op `127.0.0.1:8080`; Vite proxyt `/api` en `/storage` door.

## Login configureren

DiploPresent gebruikt een eigen loginpagina met PHP-sessiecookie. Wachtwoorden staan niet in de Vue-build.
Er zijn twee rollen:

- `admin`: vast beheerwachtwoord uit `.env`
- `user`: gedeeld gebruikerswachtwoord dat admin na gebruik handmatig kan roteren

Maak lokaal of op de server een `.env` bestand op basis van `.env.example`:

```powershell
Copy-Item .env.example .env
```

Genereer een hash voor het adminwachtwoord:

```powershell
php -r "echo password_hash('jouw-wachtwoord', PASSWORD_DEFAULT), PHP_EOL;"
```

Zet de output in `.env`:

```env
DIPLOPRESENT_ADMIN_PASSWORD_HASH="$2y$..."
DIPLOPRESENT_SESSION_NAME="diplopresent_auth"
```

Optioneel kun je ook een eerste gebruikerswachtwoord in `.env` zetten:

```env
DIPLOPRESENT_USER_PASSWORD_HASH="$2y$..."
```

Log daarna in als admin en gebruik `Beheer > Gebruikerswachtwoord roteren` om het gedeelde gebruikerswachtwoord te maken of te vervangen. Je kunt zelf een wachtwoord invullen of er automatisch een laten maken. De hash wordt opgeslagen in `storage/auth.json`; het opgeslagen wachtwoord wordt eenmalig op het scherm getoond.

Commit `.env` niet. Alleen `.env.example` hoort in Git.

## Productiebuild

```powershell
npm run build
```

Kopieer daarna de inhoud van `dist/` naar de webmap `/diplo/`. `npm run build` zet automatisch `api/`, `.htaccess`, `.env.example`, `images/` en een lege `storage/` in `dist/`. Echte runtime-data uit je lokale `storage/` wordt niet meegenomen.

De mappen `storage/imports`, `storage/lists`, `storage/photos` en `storage/sessions` moeten voor PHP leesbaar zijn. `storage/imports`, `storage/lists`, `storage/photos` en `storage/sessions` moeten ook schrijfbaar zijn voor upload/configuratie.

De root-`.htaccess` beschermt geen Basic Auth meer. Authenticatie loopt via `api/auth.php`. De `.htaccess`-regels blokkeren wel indexering, dotfiles en directe toegang tot gevoelige storage-bestanden.

## Data

- De editor begint altijd met een CSV-export uit het schoolsysteem.
- CSV gebruikt een puntkomma als scheidingsteken en een headerregel.
- Nieuwe presentatielijsten gebruiken benoemde leerlingvelden onder `students`.
- Lijsten worden per mentor, combinatie van mentoren of stamgroep samengesteld en blijven ongepagineerd, zodat de volledige volgorde met drag-and-drop kan worden bepaald.
- Een gedeelde presentatie gebruikt een tijdelijk sessiebestand in `storage/sessions`; het dashboard bestuurt het beamerbeeld via polling.
