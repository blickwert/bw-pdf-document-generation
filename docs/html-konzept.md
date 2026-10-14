# HTML-/CSS-Konzept für PDF-Templates (mPDF)

Stand: Schritt 2 der Übergabe. Alle Aussagen unter "Testergebnisse" sind mit mPDF 8.3.1 / PHP 8.3 am PoC (`poc/`) verifiziert.
Aussagen zu Elementor sind **nicht** in einer WordPress-Instanz getestet (hier gibt es keine), sondern aus dem bekannten Verhalten von Elementor und mPDF abgeleitet.

## 1. Testergebnisse

Szenarien: `php render.php --scenario=<name> --demo-last` (Datei `out/angebot-<name>.pdf`).

| Szenario | Seiten | Ergebnis |
|---|---|---|
| `mini` (1 Seite, ohne Projektablauf) | 1 | Endseiten-Footer gilt auf der einzigen Seite (wie dokumentiert). |
| `kurz` | 2 | Seite 1 ist inhaltlich voll; die Schlusszeilen rutschen auf Seite 2. Kein Fehler, aber siehe 1a. |
| `normal` | 2 | Seite 1 laufender Footer, Seite 2 Endseiten-Footer. Positionen gegen das Original auf ca. 1 px (80 dpi) genau. |
| `lang` | 3 | Seite 1 und 2 laufender Footer, Seite 3 Endseiten-Footer. |
| `tabelle` (42 Positionen) | 3 | Tabelle bricht um, Kopfzeile "Beschreibung" wiederholt sich auf Seite 3, keine zerrissenen Zeilen. |

Pflichtkatalog aus Abschnitt 5 der Übergabe:

- **1-/2-/3-seitig und lang:** bestanden (mini/normal/lang/tabelle).
- **Endseiten-Footer korrekt:** bestanden; er erscheint ausschließlich auf der letzten Seite (per `pdftotext` je Seite geprüft).
- **Keine Leerseiten:** bestanden, in keinem Szenario.
- **Tabellenkopf wiederholt sich:** bestanden (`<thead>` genügt).
- **Keine zerrissenen Zeilen:** bestanden mit `table.pos tr { page-break-inside: avoid; }`.
- **`show-this-page="1"`:** ist hier weder beim Header- noch beim Footer-Tag nötig. `@page :first` mit `header: html_first` genügt, der Footer-Tag wird ohne das Attribut gesetzt.
- **Lokale Hintergrundbilder:** funktionieren mit absolutem Pfad und `background-image-resize: 6`, ohne Zusatzeinstellung.

### 1a. Verhalten bei Seitenumbruch am Ende
`.schluss { page-break-inside: avoid }` hält "Vielen Dank …" und Name zusammen. Vorher blieb im Szenario `kurz` eine einzelne Zeile ("David Wögerer") allein auf Seite 2 zurück.
Der Endseiten-Footer erscheint dann auf einer fast leeren letzten Seite. Das ist erwartetes Verhalten; ein Plugin-Feature "Schluss nicht allein auf eigener Seite" wäre denkbar, ist aber nicht umgesetzt.

## 2. Gefundene Stolpersteine

1. **CSS-Spezifität in Header-Tabellen:** `table.hf td { padding: 0 }` überstimmte `.kontakt`/`.info`. Selektoren müssen `table.hf td.kontakt` heißen.
2. **Padding/Ausrichtung nur auf `td`, nicht auf `div`** innerhalb von Header-Tabellen. `header-first.html` ist deshalb als Tabelle mit `rowspan` aufgebaut.
3. **Kein `size:` in `@page`**, Format nur im Konstruktor (aus der Übergabe; hier nicht gegengetestet, aber ohne `size:` gab es keine Leerseiten).
4. `render.php` rastert nichts selbst; Vergleich mit dem Original per `pdftoppm -r 80 -png`. Die Vorlage hat 3 mm Beschnitt (ca. 9,4 px bei 80 dpi), das beim Überlagern abzuziehen ist.

## 3. Regelwerk: PDF-sicheres HTML/CSS (mPDF)

**Layout**
- Tabellen (`<table>`) für alles Spaltige; `float` nur für einfache Fälle. Kein Flexbox, Grid, `position: sticky`, CSS-Variablen, `calc()`, JS.
- Maße in `mm`/`pt`, keine `rem`/`vw`/`%`-Höhen.
- Abstände über `margin` auf Blockelementen und `padding` auf `td`; in Header/Footer-Tabellen nur auf `td`.
- Hintergründe der Seite über `@page { background }`, nicht über Body-Bilder.

**Selektoren**
- Einfache Klassen- und Element-Selektoren. Spezifität gegen Tabellen-Resets beachten (siehe oben).

**Seitenumbruch**
- `page-break-inside: avoid` für Zeilen und Schlussblöcke, `page-break-after/before` für harte Umbrüche, `<thead>` für wiederholte Tabellenköpfe.

**Header/Footer**
- Benannte Blöcke `<htmlpageheader name>`/`<htmlpagefooter name>`, Aktivierung per `@page { header: html_name }` oder `<sethtmlpagefooter>`.
- Endseiten-Footer am Dokumentende mit `<sethtmlpagefooter name="footerlast" value="on" />`.
- `{PAGENO}` und `{nbpg}` nur im Header/Footer.

**Schrift**
- Nur statische TTFs über `fontdata` (Lato Light/Regular/Bold hier). Variable Fonts (z. B. Open Sans Light von Google Fonts) funktionieren nicht.
- Standardschrift als `default_font` setzen; Fettdruck über eigene Family (`lato`) statt `font-weight` auf Light.

**Inhalt**
- Standard-Ausgabe immer HTML-escaped; Rohhtml (`format="raw"`) nur aus vertrauenswürdigen Quellen.

## 4. Können Elementor-Textblöcke einfließen?

**Kurzantwort: ja, aber nur einzelne Textblöcke, nicht ganze Elementor-Seiten.** (Ableitung, nicht in WordPress getestet.)

- Elementors Ausgabe (`get_builder_content_for_display()`) besteht aus verschachtelten Containern mit Flexbox, CSS-Variablen und per Klasse eingebundenem CSS. mPDF kann das nicht darstellen; das Ergebnis wäre unlesbar oder unvorhersehbar.
- Ein **Text-Editor-Widget** liefert dagegen einfaches HTML (`p`, `h2`–`h6`, `strong`, `em`, `ul/ol`, `a`, `br`). Das ist mPDF-tauglich.
- Empfohlener Weg: Inhalte (z. B. Zielsetzung, Projektschritte) als **ACF-/Meta-Felder oder in einem eigenen Editor** pflegen und über die Daten-API (`bw_pdf_render($slug, $data)`) übergeben. Bei Elementor-Ursprung die Widget-Inhalte gezielt auslesen und per `wp_kses` auf eine Whitelist (`p, br, strong, em, b, i, ul, ol, li, a, h2, h3`) reduzieren.
- Kein Weg: Theme-/Elementor-Markup direkt rendern lassen.
- Vor der Umsetzung in einer echten WP-Instanz mit einem Elementor-Textblock gegenprüfen.

## 5. Offene Punkte für Schritt 3 und später

- Schrift: Open Sans Light (Footer/Kontakt im Original) fehlt weiter; PoC nutzt durchgehend Lato Light.
- Das Original hat mehr Zeilenabstand in der Positionstabelle und eine größere Überschrift "Angebotspositionen"; im PoC nicht angeglichen.
- Zahlen im `sample-data.json` (Netto 2.248,00) hängen nicht an der Positionsanzahl; irrelevant für den Layout-Test.
