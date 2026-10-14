# Übergabe: BW PDF Generator (WordPress-Plugin)

> Einstieg in Claude Code: Diese Datei im Repo-Root ablegen, `bw-pdf-poc.zip` nach `poc/` entpacken,
> das Beispiel-PDF nach `docs/beispiel-angebot.pdf` legen und dann einfügen:
>
> **"Lies UEBERGABE.md komplett und das PoC in poc/. Starte mit Schritt 1 unter 'Vorgehen'. Antworte auf Deutsch, knapp."**

## 1. Ziel

WordPress-Plugin (Präfix `BW_` / `bw_`, Text-Domain `bw-pdf`), das aus HTML-Templates mehrseitige PDFs erzeugt.
Daten kommen dynamisch, Variablen werden per Shortcodes eingesetzt. Wie in Word gibt es getrennte Kopf- und Fußzeilen für
**erste Seite, laufende Seiten und letzte Seite**. Erster Anwendungsfall: Blickwert-Angebote
(Referenz: `docs/beispiel-angebot.pdf`, Nr. AN-2026-02). Später ggf. weitere Dokumenttypen.

## 2. Getroffene Entscheidungen

- **Bibliothek: mPDF (^8.2)**, reines PHP, GPL-2.0 (für ein WP-Plugin unkritisch).
  Warum: Kennt `@page`, `@page :first`, benannte Seiten, benannte HTML-Header/Footer, `{PAGENO}`/`{nbpg}`.
  Dompdf scheidet aus (keine wiederholten Header/Footer, ignoriert Margin-Boxen). Chromium/WeasyPrint scheiden aus,
  weil der Ziel-Server (Goserver, Shared Hosting, ein SSH-User) keine Binaries/Node/Python-Dienste bietet.
- **Kein Theme-/Elementor-HTML.** mPDF kann kein Flexbox, Grid, CSS-Variablen, JS. PDF-Templates sind eigenes,
  tabellen-/float-basiertes HTML+CSS. Elementor-Inhalte höchstens als einzelne Textblöcke einspeisen (zu verifizieren).
- **Seitenlogik:** `@page` = laufende Seiten, `@page :first` = Erstseite. Es gibt kein `@page :last`:
  Endseiten-Footer per `<sethtmlpagefooter name="footerlast" value="on" />` am Dokumentende.
  Bei einseitigen Dokumenten gilt dadurch der Endseiten-Footer (Verhalten dokumentieren, ggf. optionales Feld "Footer einseitig").
- **Dekoration als Seitenhintergrund:** `@page { background: url(...) no-repeat 0 0; background-image-resize: 6; }`,
  je ein Hintergrund für Seite 1 und Folgeseiten. Text bleibt live in HTML (markierbar).
- **Kein `size:` in `@page`**, Format im mPDF-Konstruktor (`'format' => 'A4'`). Ein Testbericht nennt sonst massenhaft Leerseiten.
- **Variablen:** Shortcodes `[bw_var key="a.b" format="raw|money|date|nl2br"]`, `[bw_each source="x"]…[/bw_each]`,
  `[bw_if key="x"]…[/bw_if]`. Standard-Ausgabe HTML-escaped. Beträge im Format de_AT (`€ 2.248,00`).
- **Daten über API, nicht aus dem Template:** `bw_pdf_render( string $template_slug, array $data, array $args = [] )`;
  Filter `bw_pdf_data_{$doctype}` zum Anreichern (z. B. USt/Brutto berechnen).

## 3. Stand (PoC in `poc/`, aus dem Chat erzeugt)

Fertig und geprüft:
- Grafiken aus dem Beispiel-PDF extrahiert (Streifen, Logo, Icons, Farbfächer, Berge), Position/Größe per Composite gegen das Original geprüft.
- Template `templates/angebot/` (template, header-first, header-running, footer-running, footer-last, style.css, sample-data.json).
- `src/Shortcodes.php` (Mini-Engine, im Plugin durch `add_shortcode`/`do_shortcode` zu ersetzen), `render.php` (CLI: `--scenario=kurz|normal|lang`, `--demo-last`, `--html-only`).
- HTML-Zusammenbau getestet: keine offenen Platzhalter, Summen 2.248,00 / 449,60 / 2.697,60.

**Nicht getestet: das eigentliche PDF-Rendering mit mPDF.** In der Chat-Umgebung war `repo.packagist.org` gesperrt.
Lokal sollte `composer install` funktionieren. Alles Layout (Abstände, Header-Höhe, Hintergründe) ist daher bisher Annahme.

Maße: Vorlage ist A4 + 3 mm Beschnitt (216 × 303 mm), das Template rechnet auf A4 netto um.
Schrift: Original Lato Light + Open Sans Light (Footer/Kontakt); PoC nutzt durchgehend Lato Light (TTFs in `poc/fonts/`).
Open Sans Light gibt es bei Google Fonts nur variabel, mPDF braucht statische TTFs (selbst besorgen oder bei Lato bleiben).

## 4. Anforderungen ans Plugin

- Composer-Abhängigkeiten gebündelt (`vendor/` im Plugin). Konflikte mit anderen Plugins, die mPDF bündeln (z. B. Gravity PDF),
  vermeiden: Strauss/PHP-Scoper-Prefixing oder `class_exists`-Fallback, Entscheidung kurz begründen.
- PHP-Version und Extensions (mbstring, gd) beim Aktivieren prüfen, sonst Admin-Notice.
- `tempDir` dediziert unter `wp-content/uploads/bw-pdf/tmp/` mit `.htaccess` (Deny) und `index.php`;
  Schriften über `fontDir`/`fontdata`. PHP-Speicherlimit beachten.
- Template-Verwaltung: CPT `bw_pdf_template` (nicht öffentlich, Code-Editor statt Elementor) mit Feldern für Body, CSS,
  Header Erstseite, Header laufend, Footer laufend, Footer letzte Seite, Seitenformat/Ränder, Dokumenttyp.
  Alternativ Dateien im Theme mit Override-Hierarchie: entscheiden und begründen. Admin-Vorschau mit Beispieldaten.
- Hintergründe pro Template konfigurierbar (Seite 1 / Folgeseiten); Logo, Farben, Firmendaten zentral in Settings.
- Ausgabe in `uploads/bw-pdf/` (geschützt), Auslieferung nur über Endpoint (admin-post/REST) mit Nonce/Token + Capability-Check.
  Template-HTML nur für `manage_options` editierbar.
- Unbekannte Shortcode-Keys: Verhalten konfigurierbar (leer, Platzhalter, Log). Render-Kontext pro Aufruf isoliert.
- Code: WordPress Coding Standards, Escaping, Nonces, `BW_`-Klassen.

## 5. Bekannte Risiken und Korrekturen

- `show-this-page="1"` ist laut Gravity-PDF-Doku beim **Header**-Tag nötig. Ob es beim Footer-Tag nötig/erlaubt ist, im Test klären.
- Der Test-Pflichtkatalog: 1-, 2-, 3-seitige und lange Dokumente; Endseiten-Footer korrekt; keine Leerseiten;
  Tabellenkopf wiederholt sich beim Umbruch; keine zerrissenen Tabellenzeilen.
- Lokale Hintergrundbilder in mPDF: Pfad absolut, ggf. Einstellung für lokale Dateien prüfen.
- Beispieltext im PoC enthält korrigierte Tippfehler der Vorlage ("gelten", "bestehendem", "kundenseitiger", "Micha Kriebernegg").
  "Raiffeisenbank" in der Vorlage ist korrekt geschrieben.

## 6. Vorgehen

1. `cd poc && composer install && php render.php --scenario=normal --demo-last`; PDF als Bild rastern (`pdftoppm -r 80 -png`)
   und gegen `docs/beispiel-angebot.pdf` legen. Abweichungen (Header-Höhe, Abstände, Hintergrund, Endseiten-Footer) beheben.
2. Szenarien `kurz` und `lang` prüfen, Pflicht-Tests aus Abschnitt 5 abarbeiten, Ergebnis in `docs/html-konzept.md` festhalten
   (inkl. Antwort, ob und wie Elementor-Textblöcke einfließen können, und ein Regelwerk für PDF-sicheres HTML/CSS).
3. Plan für Plugin-Architektur und Dateistruktur vorlegen (CPT vs. Dateien, Namespace-Prefixing). Danach freigeben lassen.
4. Plugin-Gerüst, Shortcode-Engine mit `add_shortcode`, Template-CPT, Endpoint, Admin-Vorschau.
5. Default-Template `angebot`, Tests, `README.md` mit Shortcode-Referenz.

Fragen nur bei echten Blockern; sonst entscheiden, begründen, dokumentieren.
