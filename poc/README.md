# BW PDF – Proof of Concept (mPDF)

HTML-Template + Shortcodes -> mehrseitiges PDF mit eigener Erstseite, laufenden Seiten und Endseite.
Vorlage: `AN-2026-02-kriebernegg.pdf` (A4 + 3 mm Beschnitt; hier auf A4 netto umgerechnet).

## Status

| Teil | Stand |
|---|---|
| Grafiken aus dem PDF extrahiert, Position/Größe geprüft (Composite gegen Original) | fertig |
| Template (HTML/CSS), Shortcode-Engine, Render-Skript | fertig, HTML-Zusammenbau getestet (0 offene Platzhalter, Summen korrekt) |
| **PDF-Rendering mit mPDF** | **nicht getestet**: `repo.packagist.org` war in der Entwicklungsumgebung gesperrt |

Erster Schritt bei dir: `composer install`, dann `php render.php --scenario=normal --demo-last`.

## Aufbau

```
templates/angebot/
  template.html        Body (Titel, Abschnitte, Positionstabelle, Schluss)
  header-first.html    Header nur Seite 1 (Adresse, Kontakt, Infobox-Text)
  header-running.html  Header Seite 2..n (optional: Nr. + "Seite x von y")
  footer-running.html  Footer Seiten 1..n-1
  footer-last.html     Footer letzte Seite
  style.css            @page-Regeln + Styles (mPDF-tauglich)
  sample-data.json     Beispieldaten
assets/bg-first.jpg    Seite-1-Hintergrund (Streifen, Logo, Icons, Infobox-Fläche, Berge, Ornament)
assets/bg-folge.jpg    Hintergrund Folgeseiten (Streifen, Berge, Ornament)
assets/komponenten/    Einzelteile als transparente PNG + assets.json (Positionen in mm)
src/Shortcodes.php     Mini-Engine; im Plugin ersetzt durch add_shortcode()/do_shortcode()
render.php             CLI: --scenario=kurz|normal|lang, --demo-last, --html-only
```

## Wie die Seitenlogik funktioniert

- `@page` gilt für alle Seiten, `@page :first` überschreibt Header, Rand und Hintergrund der Seite 1.
- Header/Footer sind benannte Blöcke: `<htmlpageheader name="first">`, referenziert per `header: html_first;`.
- Es gibt kein `@page :last`: Der Endseiten-Footer wird am Dokumentende mit
  `<sethtmlpagefooter name="footerlast" value="on" />` gesetzt. Bei einseitigen Dokumenten gilt er daher auch für Seite 1.
- Hintergrundbilder: `background: url(...) no-repeat 0 0; background-image-resize: 6;` im `@page`.
- Kein `size:` im `@page` (Format kommt aus dem Konstruktor): ein Testbericht nennt für mPDF sonst massenhaft Leerseiten.

## Zu prüfen, sobald mPDF läuft

1. Greifen `@page`-Hintergründe (Pfad absolut, lokale Dateien erlaubt)?
2. Endseiten-Footer bei 1, 2, 3 und langen Seiten (`--scenario`), `--demo-last` macht ihn sichtbar. Ob `show-this-page="1"` auch beim Footer nötig ist, klärt der Test.
3. Keine Leerseiten; Tabellenkopf wiederholt sich beim Umbruch.
4. Header-Höhe Seite 1 (Adresse/Infobox sitzen bei y = 52,4 / 83,9 mm) gegen das Original legen.
5. Schrift: Original nutzt Lato Light + Open Sans Light (Footer, Kontakt); hier durchgehend Lato Light.

## Inhaltliche Korrekturen im Beispieltext

"gellten" -> "gelten", "bestehendne" -> "bestehendem Corporate Design", "Kundseitiger" -> "kundenseitiger",
"MichaKriebernegg" -> "Micha Kriebernegg", "3," -> "3.", doppelte Leerzeichen entfernt.
