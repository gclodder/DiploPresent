# DiploPresent 3

Vue 3 + Vite + Tailwind frontend met een PHP JSON-API. Node.js is alleen nodig voor ontwikkeling en de productiebuild; de host serveert daarna statische bestanden en PHP.

## Lokaal ontwikkelen

```powershell
npm install
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

## Productiebuild

```powershell
npm run build
```

Kopieer daarna de inhoud van `dist/` naar de webmap `/diplo/` en plaats `api/`, `storage/` en `.htaccess` ernaast. De map `images/` zit al in de build.

De mappen `storage/imports`, `storage/lists`, `storage/photos` en `storage/sessions` moeten voor PHP leesbaar zijn. `storage/imports`, `storage/lists` en `storage/sessions` moeten ook schrijfbaar zijn.

De root-`.htaccess` beschermt de volledige app met HTTP Basic Authentication. Pas vóór deployment het absolute pad bij `AuthUserFile` aan wanneer de hostinglocatie afwijkt.

## Data

- De editor begint altijd met een CSV-export uit het schoolsysteem.
- CSV gebruikt een puntkomma als scheidingsteken en een headerregel.
- Nieuwe presentatielijsten gebruiken benoemde leerlingvelden onder `students`.
- Lijsten worden per mentor, combinatie van mentoren of stamgroep samengesteld en blijven ongepagineerd, zodat de volledige volgorde met drag-and-drop kan worden bepaald.
- Een gedeelde presentatie gebruikt een tijdelijk sessiebestand in `storage/sessions`; het dashboard bestuurt het beamerbeeld via polling.
