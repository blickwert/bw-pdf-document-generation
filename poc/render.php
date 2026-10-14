<?php
/**
 * Proof of Concept: Template "angebot" -> PDF (mPDF).
 *
 * Aufruf:  php render.php [--scenario=mini|kurz|normal|lang|tabelle] [--demo-last] [--html-only]
 *   kurz    ~1 Seite  (Test: Erst- und Endseite sind dieselbe Seite)
 *   normal  ~2 Seiten (wie das Beispiel-PDF)
 *   lang    3+ Seiten (Test: laufende Seiten, Tabellenumbruch)
 *   --demo-last  setzt eine sichtbare Zusatzzeile im Endseiten-Footer, damit man ihn im PDF erkennt
 *   --html-only  nur das zusammengesetzte HTML schreiben (auch automatisch, wenn mPDF nicht installiert ist)
 */
require __DIR__ . '/src/Shortcodes.php';

$opt      = getopt('', ['scenario::', 'demo-last', 'html-only']);
$scenario = $opt['scenario'] ?? 'normal';
$tplDir   = __DIR__ . '/templates/angebot';
$outDir   = __DIR__ . '/out';
@mkdir($outDir . '/tmp', 0775, true);

/* ---------- 1) Daten ---------- */
$data = json_decode(file_get_contents($tplDir . '/sample-data.json'), true, 512, JSON_THROW_ON_ERROR);

if ($scenario === 'mini') {
    $data['schritte']   = [];
    $data['projektablauf_intro'] = '';
    $data['positionen'] = array_slice($data['positionen'], 0, 2);
} elseif ($scenario === 'tabelle') {
    // Tabelle läuft über den Seitenumbruch: Kopfwiederholung / zerrissene Zeilen prüfen
    $data['positionen'] = array_merge(...array_fill(0, 7, $data['positionen']));
} elseif ($scenario === 'kurz') {
    $data['schritte']   = array_slice($data['schritte'], 0, 1);
    $data['positionen'] = array_slice($data['positionen'], 0, 2);
} elseif ($scenario === 'lang') {
    $data['schritte']   = array_merge($data['schritte'], $data['schritte'], $data['schritte']);
    $data['positionen'] = array_merge($data['positionen'], $data['positionen'], $data['positionen']);
}
if (isset($opt['demo-last'])) {
    $data['dokument']['schluss_hinweis'] = '[ENDSEITEN-FOOTER AKTIV]';
}

// abgeleitete Werte (im Plugin: Filter bw_pdf_data_angebot)
$netto = (float) $data['summen']['netto'];
$data['summen']['ust']    = round($netto * $data['summen']['ust_satz'] / 100, 2);
$data['summen']['brutto'] = round($netto + $data['summen']['ust'], 2);

/* ---------- 2) Template-Teile rendern ---------- */
$sc   = new BW_Shortcodes_Lite($data);
$part = static function (string $file) use ($sc, $tplDir): string {
    return $sc->render(file_get_contents($tplDir . '/' . $file));
};

$css = str_replace('{{ASSETS}}', __DIR__ . '/assets', file_get_contents($tplDir . '/style.css'));

$html  = '<htmlpageheader name="first">'    . $part('header-first.html')    . '</htmlpageheader>';
$html .= '<htmlpageheader name="running">'  . $part('header-running.html')  . '</htmlpageheader>';
$html .= '<htmlpagefooter name="footer">'   . $part('footer-running.html')  . '</htmlpagefooter>';
$html .= '<htmlpagefooter name="footerlast">' . $part('footer-last.html')   . '</htmlpagefooter>';
$html .= $part('template.html');
// Endseiten-Footer: es gibt kein @page :last, daher wird er am Dokumentende gesetzt.
$html .= '<sethtmlpagefooter name="footerlast" value="on" />';

$base = "angebot-$scenario";
file_put_contents("$outDir/$base.html", "<style>\n$css\n</style>\n$html");
echo "HTML geschrieben: out/$base.html\n";

/* ---------- 3) PDF (nur wenn mPDF installiert ist) ---------- */
$autoload = __DIR__ . '/vendor/autoload.php';
if (isset($opt['html-only']) || !is_file($autoload)) {
    fwrite(STDERR, "mPDF nicht installiert (vendor/autoload.php fehlt) -> PDF übersprungen. 'composer install' ausführen.\n");
    exit(is_file($autoload) ? 0 : 2);
}
require $autoload;

$defaultConfig     = (new Mpdf\Config\ConfigVariables())->getDefaults();
$defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();

$mpdf = new Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',           // Format hier, NICHT per @page { size } (siehe README)
    'orientation'   => 'P',
    'margin_left'   => 20,
    'margin_right'  => 20,
    'margin_top'    => 28.8,
    'margin_bottom' => 33,
    'margin_header' => 0,
    'margin_footer' => 18.5,
    'tempDir'       => $outDir . '/tmp',
    'fontDir'       => array_merge($defaultConfig['fontDir'], [__DIR__ . '/fonts']),
    'fontdata'      => $defaultFontConfig['fontdata'] + [
        'lato'      => ['R' => 'Lato-Regular.ttf', 'B' => 'Lato-Bold.ttf', 'I' => 'Lato-Italic.ttf'],
        'latolight' => ['R' => 'Lato-Light.ttf', 'I' => 'Lato-LightItalic.ttf'],
    ],
    'default_font'      => 'latolight',
    'default_font_size' => 9,
]);
$mpdf->SetTitle($data['dokument']['titel']);
$mpdf->SetAuthor($data['firma']['inhaber'] . ' – ' . $data['firma']['marke']);

$mpdf->WriteHTML($css, Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html, Mpdf\HTMLParserMode::HTML_BODY);
$mpdf->Output("$outDir/$base.pdf", Mpdf\Output\Destination::FILE);
echo "PDF geschrieben: out/$base.pdf (" . $mpdf->page . " Seiten)\n";
