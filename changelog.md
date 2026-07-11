# Changelog

## 2026-07-11

### Release 3.3.0

- Versienummer verhoogd naar `3.3.0`.
- Nieuw distributiepakket gemaakt: `DiploPresent 3.3.0 - 20260711-1541.zip`.

### Presenter en live-weergave

- Na de laatste leerling keert de presentatie automatisch terug naar het groepsfotoscherm.
- In het dashboard gaat `volgende` na de laatste leerling ook terug naar het groepsfotoscherm, zodat gedeelde presentaties hetzelfde gedrag hebben.
- Op het groepsfotoscherm start toets `F` een fly-by met alle `_2` leerlingfoto's uit de actieve presentatielijst.
- Tijdens de fly-by faden titel, groepsfoto, schoollogo en teller tijdelijk weg, zodat de foto's over de blauwe achtergrond vliegen.
- Fly-by foto's starten volledig buiten beeld rechts, vliegen lineair van rechts naar links en verdwijnen volledig buiten beeld links.
- De fly-by is vertraagd, zodat foto's beter herkenbaar zijn, en de foto's zelf faden niet in of uit.

### Login en beheer

- Eigen loginflow toegevoegd via PHP-sessies in plaats van Basic Auth.
- Rollen toegevoegd voor `admin` en gedeelde `user`.
- Adminwachtwoord wordt via `.env` ingesteld met een wachtwoordhash.
- Beheerscherm uitgebreid met het roteren van het gedeelde gebruikerswachtwoord.
- Gegenereerde gebruikerswachtwoorden worden eenmalig getoond en als hash opgeslagen in `storage/auth.json`.
- Frontend en API-client aangepast op loginstatus, logout en rolgebaseerde toegang.

### Beheer van groepsfoto's

- Beheerscherm uitgebreid met upload, preview en verwijderen van groepsfoto's per afdeling.
- Groepsfoto's worden opgeslagen als `examenfoto_havo.jpg` en `examenfoto_vwo.jpg`.
- Uploadflow ondersteunt bijsnijden van groepsfoto's voordat ze worden opgeslagen.
- API-endpoints voor groepsfoto's en configuratie aangescherpt.

### Editor en lijsten

- Editor verder uitgebreid voor het samenstellen en beheren van presentatielijsten.
- Lijsten kunnen per mentor, mentorcombinatie of stamgroep worden samengesteld.
- Drag-and-drop volgorde blijft leidend en lijsten blijven ongepagineerd bij bewerken.
- Printweergave verbeterd met compacte en gebalanceerde lijstlayouts.
- Bestands- en lijstacties visueel aangescherpt.

### Productiebuild en serverbestanden

- `npm run build` bereidt `dist/` automatisch voor met `api/`, `.htaccess`, `.env.example`, afbeeldingen en lege `storage/`-mappen.
- Runtime-data uit lokale `storage/` wordt niet meegenomen in de distributiebuild.
- `.htaccess`-regels toegevoegd/aangescherpt om indexering, dotfiles, gevoelige JSON-bestanden en PHP-uploadmisbruik in `storage/` te blokkeren.
- README bijgewerkt met ontwikkel-, login- en productie-instructies.

### Releases en distributiepakketten

- Releasepakket `DiploPresent 3.2.0 - 20260711-1440.zip` aangemaakt.
- Releasepakket `DiploPresent 3.2.0 - 20260711-1540.zip` aangemaakt.
- Releasepakket `DiploPresent 3.3.0 - 20260711-1541.zip` aangemaakt.
