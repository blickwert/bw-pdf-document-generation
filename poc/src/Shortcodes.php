<?php
/**
 * Mini-Shortcode-Engine für den Proof of Concept (läuft ohne WordPress).
 *
 * Im Plugin übernehmen add_shortcode() + do_shortcode() genau diese Aufgabe.
 * Die Shortcode-Namen und Attribute hier sind die Spezifikation fürs Plugin:
 *
 *   [bw_var key="kunde.kuerzel" format="raw|money|date|nl2br"]   Einzelwert (Standard: HTML-escaped)
 *   [bw_each source="positionen"] ... [/bw_each]               Wiederholung über ein Array von Zeilen
 *   [bw_if key="dokument.hinweis"] ... [/bw_if]                Block nur bei nicht-leerem Wert
 *
 * Innerhalb von bw_each lösen sich Keys zuerst gegen die aktuelle Zeile auf,
 * danach gegen den Gesamt-Datensatz.
 */
final class BW_Shortcodes_Lite
{
    /** @var array<string,mixed> */
    private $data;

    /** @param array<string,mixed> $data */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function render(string $tpl): string
    {
        $tpl = $this->each($tpl);
        $tpl = $this->ifs($tpl);
        return $this->vars($tpl);
    }

    private function each(string $tpl): string
    {
        return preg_replace_callback(
            '/\[bw_each source="([^"]+)"\](.*?)\[\/bw_each\]/s',
            function (array $m): string {
                $rows = $this->get($m[1], true);
                if (!is_array($rows)) {
                    return '';
                }
                $out = '';
                foreach ($rows as $row) {
                    $scope = new self(array_merge($this->data, is_array($row) ? $row : ['wert' => $row]));
                    $out .= $scope->render($m[2]);
                }
                return $out;
            },
            $tpl
        );
    }

    private function ifs(string $tpl): string
    {
        return preg_replace_callback(
            '/\[bw_if key="([^"]+)"\](.*?)\[\/bw_if\]/s',
            function (array $m): string {
                $v = $this->get($m[1], true);
                return (is_array($v) ? count($v) > 0 : trim((string) $v) !== '') ? $m[2] : '';
            },
            $tpl
        );
    }

    private function vars(string $tpl): string
    {
        return preg_replace_callback(
            '/\[bw_var key="([^"]+)"(?:\s+format="([^"]+)")?\]/',
            function (array $m): string {
                $v = $this->get($m[1], true);
                if (is_array($v)) {
                    return '';
                }
                $v = (string) $v;
                switch ($m[2] ?? '') {
                    case 'raw':
                        return $v;                                   // nur für vertrauenswürdige Felder
                    case 'nl2br':
                        return nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
                    case 'money':
                        return '€&nbsp;' . number_format((float) $v, 2, ',', '.');   // de_AT: 2.248,00
                    case 'date':
                        $t = strtotime($v);
                        return $t ? date('d. m. Y', $t) : htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
                    default:
                        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
                }
            },
            $tpl
        );
    }

    /** Dot-Notation-Lookup, z. B. "kunde.kuerzel". Unbekannte Keys liefern ''. */
    private function get(string $key, bool $keepArray = false)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
        $cur = $this->data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return '';
            }
            $cur = $cur[$part];
        }
        return $cur;
    }
}
