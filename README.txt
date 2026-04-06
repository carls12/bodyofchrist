INSTALL (Shared Hosting)

1) MySQL DB erstellen (z.B. bodyofchrist) und database/schema.sql importieren.

2) DB Zugangsdaten setzen:
   - Entweder in app/config.php eintragen
   - oder als ENV Variablen: DB_HOST, DB_NAME, DB_USER, DB_PASS
   Subfolder URL:
   - Wenn die App unter https://domain.tld/bodyofchrist/public läuft:
     setze APP_BASE_URL=/bodyofchrist/public

3) Upload:
   - Lade den kompletten Ordner hoch.
   - Ideal: DocumentRoot auf /public setzen.
   - Sonst: rufe die Seite über /public auf.

4) Gemeindeleiter:
   - Setze SUPER_ADMIN_EMAIL in app/config.php (deine Email).
   - Dann einloggen und /admin/users öffnen -> Nutzer als Leiter setzen.

5) Church Summary:
   - Leiter öffnet seine Gruppe -> Download Church Summary.
   - HTML kann direkt als PDF gedruckt werden.
   - Optional: echtes PDF mit DOMPDF:
     Lokal im Projektordner: composer install --no-dev
     Danach vendor/ mit hochladen.
