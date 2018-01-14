<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Statistic_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public static function array2string($array, $addquotes = TRUE)
    {

        if ($addquotes) {
            $str = implode("','", $array);
            return "'" . $str . "'";
        } else {
            $str = implode(",", $array);
            return $str;
        }
    }

    function merge($arr)
    {
        $n = array();
        if (!empty($arr)) {
            foreach ($arr as $a) {
                if (!empty($a)) {
                    foreach ($a as $k => $v) {
                        $n[$k][] = $v;
                    }
                }
            }
        }
        return $n;
    }


    /*
     * CACHE QUERY
     */
    public function Ilezostalodootrzymania_monit($nabywcy)
    {
        $query = $this->db->query("SELECT id_platnosci,pozostala_kwota,`przychody`.`numer`,datediff(`przychody`.`termin_platnosci`,NOW()) as ddif,termin_platnosci FROM `przychody_platnosci`
JOIN przychody ON przychody_platnosci.fk_przychodu = przychody.id_przychodu
WHERE `przychody`.`fk_kontrahent` = " . $nabywcy . " AND pozostala_kwota > 0 AND datediff(`przychody`.`termin_platnosci`,NOW()) < 0
Union ALL
SELECT 'SUMA' id_platnosci, SUM(pozostala_kwota), 'blank','blank','' FROM `przychody_platnosci`
JOIN przychody ON przychody_platnosci.fk_przychodu = przychody.id_przychodu
WHERE `przychody`.`fk_kontrahent` = " . $nabywcy . " AND pozostala_kwota > 0 AND datediff(`przychody`.`termin_platnosci`,NOW()) < 0");
        return $query->result_array();
    }


    public function Ilezostalodootrzymania($nabywcy)
    {
        $query = $this->db->query("SELECT id_platnosci,pozostala_kwota,`przychody`.`numer`,datediff(`przychody`.`termin_platnosci`,NOW()) as ddif,termin_platnosci FROM `przychody_platnosci`
JOIN przychody ON przychody_platnosci.fk_przychodu = przychody.id_przychodu
WHERE `przychody`.`fk_kontrahent` = " . $nabywcy . " AND pozostala_kwota > 0
Union ALL
SELECT 'SUMA' id_platnosci, SUM(pozostala_kwota), 'blank','blank','' FROM `przychody_platnosci`
JOIN przychody ON przychody_platnosci.fk_przychodu = przychody.id_przychodu
WHERE `przychody`.`fk_kontrahent` = " . $nabywcy . " AND pozostala_kwota > 0");
        return $query->result_array();
    }

    public function Ilezostalodozaplaty($kontrahentowi)
    {
        $query = $this->db->query("SELECT id_platnosci,pozostala_kwota,`wydatki`.`dokument` FROM `platnosci`
JOIN wydatki ON platnosci.fk_wydatek = wydatki.id_wydatku
WHERE wydatki.kontrahent = " . $kontrahentowi . " AND pozostala_kwota > 0
Union ALL
SELECT 'SUMA' id_platnosci, SUM(pozostala_kwota), 'blank' FROM `platnosci`
LEFT JOIN wydatki ON platnosci.fk_wydatek = wydatki.id_wydatku
WHERE wydatki.kontrahent = " . $kontrahentowi . " AND pozostala_kwota > 0");
        return $query->result_array();
    }

    public function oplacone_wydatki_wykres()
    {
        $this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
        if ($cached = !$this->cache->get('oplacone_wydatki_wykres')) {
            $query = $this->db->query("
                SELECT data_zakupu, SUM(platnosci.zaplacona_kwota) as lacznie FROM `wydatki` 
                
                LEFT JOIN platnosci ON wydatki.id_wydatku = platnosci.fk_wydatek 
                WHERE wydatki.data_zakupu >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH
                AND platnosci.zaplacona_kwota > 0
                GROUP BY data_zakupu ORDER BY `wydatki`.`data_zakupu` 
            ");
            $this->cache->save('oplacone_wydatki_wykres', $query->result_array(), 600, TRUE);
        }
        return $this->cache->get('oplacone_wydatki_wykres');
    }

    public function oplacone_przychody_wykres()
    {
        $this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
        if ($cached = !$this->cache->get('oplacone_przychody_wykres')) {
            $query = $this->db->query("
                SELECT z_dnia, SUM(przychody_platnosci.otrzymana_kwota) as lacznie FROM `przychody` 
                
                 LEFT JOIN przychody_platnosci ON przychody.id_przychodu = przychody_platnosci.fk_przychodu
                WHERE przychody.z_dnia >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH
                AND przychody_platnosci.otrzymana_kwota > 0
                GROUP BY z_dnia ORDER BY `przychody`.`z_dnia` 
            ");
            $this->cache->save('oplacone_przychody_wykres', $query->result_array(), 600, TRUE);
        }
        return $this->cache->get('oplacone_przychody_wykres');
    }

    public function ostatnie_przychody_ten_miesiac()
    {
        $this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
        if ($cached = !$this->cache->get('ostatnie_przychody_ten_miesiac')) {
            $query = $this->db->query("
                SELECT wartosc,z_dnia,numer,id_przychodu
                FROM `przychody` 
                 LEFT JOIN przychody_platnosci ON przychody.id_przychodu = przychody_platnosci.fk_przychodu
                 WHERE przychody.z_dnia >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH
                AND przychody_platnosci.status != 2
                ORDER BY `przychody`.`z_dnia`  DESC
               
            ");
            $this->cache->save('ostatnie_przychody_ten_miesiac', $query->result_array(), 600, TRUE);
        }
        return $this->cache->get('ostatnie_przychody_ten_miesiac');
    }

    public function przychody_wkres_lin()
    {
        $this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
        if ($cached = !$this->cache->get('przychody_wkres_lin')) {
            $query = $this->db->query("
                SELECT z_dnia, SUM(wartosc) as lacznie FROM `przychody` 
                
                LEFT JOIN przychody_platnosci ON przychody.id_przychodu = przychody_platnosci.fk_przychodu
                WHERE przychody.z_dnia >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH
                GROUP BY z_dnia ORDER BY `przychody`.`z_dnia` 
            ");
            $this->cache->save('przychody_wkres_lin', $query->result_array(), 600, TRUE);
        }
        return $this->cache->get('przychody_wkres_lin');
    }

    public function wydatki_wkres_lin()
    {
        $this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
        if ($cached = !$this->cache->get('wydatki_wkres_lin')) {
            $query = $this->db->query("
                SELECT data_zakupu, SUM(kwota_brutto) as lacznie FROM `wydatki` 
                
                LEFT JOIN platnosci ON wydatki.id_wydatku = platnosci.fk_wydatek 
                WHERE wydatki.data_zakupu >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH
                GROUP BY data_zakupu ORDER BY `wydatki`.`data_zakupu` 
            ");
            $this->cache->save('wydatki_wkres_lin', $query->result_array(), 600, TRUE);
        }
        return $this->cache->get('wydatki_wkres_lin');
    }

    public function ostatnie_wydatki_ten_miesiac()
    {
        $this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
        if ($cached = !$this->cache->get('ostatnie_wydatki_ten_miesiac')) {
            $query = $this->db->query("
                SELECT kwota_brutto,data_zakupu,dokument,id_wydatku 
                FROM `wydatki` 
                LEFT JOIN platnosci ON wydatki.id_wydatku = platnosci.fk_wydatek
                WHERE platnosci.status != 2 AND 
                 wydatki.data_zakupu >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH
                ORDER BY `wydatki`.`data_zakupu`  DESC
                
            ");
            $this->cache->save('ostatnie_wydatki_ten_miesiac', $query->result_array(), 600, TRUE);
        }
        return $this->cache->get('ostatnie_wydatki_ten_miesiac');
    }

    public function glowna_staty_wydatki_kategorie()
    {
        $this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
        if ($cached = !$this->cache->get('stat_w_k')) {
            $query = $this->db->query("
             SELECT cat.id_kat as cat_id,
           cat.nazwa as Category,
           coalesce(month0.tot, 0) AS ThisMonth,
           coalesce(month1.tot, 0) AS LastMonth,
           coalesce(month2.tot, 0) AS PrevMonth
           
    FROM wydatki_kategorie cat
    LEFT JOIN
      (SELECT wydatki_wpisy.kategoria,
              sum(wydatki_wpisy.brutto) AS tot
       FROM wydatki_wpisy
       LEFT JOIN wydatki ON wydatki_wpisy.do_wydatku = wydatki.id_wydatku
       WHERE wydatki.data_zakupu >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH
       GROUP BY wydatki_wpisy.kategoria
       ) month0 ON cat.id_kat = month0.kategoria
    LEFT JOIN
      (SELECT wydatki_wpisy.kategoria,
              sum(wydatki_wpisy.brutto) AS tot
       FROM wydatki_wpisy
       LEFT JOIN wydatki ON wydatki_wpisy.do_wydatku = wydatki.id_wydatku
       WHERE wydatki.data_zakupu >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 2 MONTH
         AND wydatki.data_zakupu  < LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH
       GROUP BY wydatki_wpisy.kategoria
      ) month1 ON cat.id_kat = month1.kategoria
     LEFT JOIN
      (SELECT wydatki_wpisy.kategoria,
              sum(wydatki_wpisy.brutto) AS tot
       FROM wydatki_wpisy
       LEFT JOIN wydatki ON wydatki_wpisy.do_wydatku = wydatki.id_wydatku
       WHERE wydatki.data_zakupu >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 3 MONTH
         AND wydatki.data_zakupu  < LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 2 MONTH
       GROUP BY wydatki_wpisy.kategoria
      ) month2 ON cat.id_kat = month2.kategoria
            ");
            $this->cache->save('stat_w_k', $query->result_array(), 60, TRUE);
        }
        // return $query->result_array();
        return $this->cache->get('stat_w_k');
    }

    /*
     * CACHE QUERY
     */

    public function StatystykiPracownika_Wartosci_Place($p)
    {

        $sql = "SELECT cat.id_kat as cat_id,
           cat.nazwa as Category,
           coalesce(month0.tot, 0) AS ThisMonth
           
    FROM wydatki_kategorie cat
    LEFT JOIN
      (SELECT wydatki_wpisy.kategoria,
              sum(wydatki_wpisy.brutto) AS tot
       FROM wydatki_wpisy
       LEFT JOIN wydatki ON wydatki_wpisy.do_wydatku = wydatki.id_wydatku
       
       ";

        $sql2 = " GROUP BY wydatki_wpisy.kategoria
       ) month0 ON cat.id_kat = month0.kategoria";
        $sql3 = "";

        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customYear'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $sql3 .= " WHERE ";
            $sql3 .= "wydatki.data_zakupu >= '" . date('Y-m-01', strtotime($query_date)) . "' AND ";
            $sql3 .= "wydatki.data_zakupu <= '" . date('Y-m-t', strtotime($query_date)) . "' AND ";
            $sql3 .= "wydatki.id_kupujacy = '" . $p . "' ";
            $sql3 .= "";
        }

        $query = $this->db->query($sql . "" . $sql3 . "" . $sql2);

        return $query->result_array();
    }

    /* Analityka */

    /*
     * Row 0 - global
     * Row 1 - this month/chosen month
     */

    function rangeMonth($datestr)
    {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('Y-m-d', strtotime('first day of this month', $dt));
        $res['end'] = date('Y-m-d', strtotime('last day of this month', $dt));
        return $res;
    }

    function rangeWeek($datestr)
    {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('N', $dt) == 1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt));
        $res['end'] = date('N', $dt) == 7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt));
        return $res;
    }

    public static function mnum_mname($nr, $type = "quoted")
    {
        $date['months'][1] = 'styczeń';
        $date['months'][2] = 'luty';
        $date['months'][3] = 'marzec';
        $date['months'][4] = 'kwiecień';
        $date['months'][5] = 'maj';
        $date['months'][6] = 'czerwiec';
        $date['months'][7] = 'lipiec';
        $date['months'][8] = 'sierpień';
        $date['months'][9] = 'wrzesień';
        $date['months'][10] = 'październik';
        $date['months'][11] = 'listopad';
        $date['months'][12] = 'grudzień';
        if ($type === "quoted") {
            return "'" . ucfirst($date['months'][$nr]) . "'";
        } else {
            return ucfirst($date['months'][$nr]);
        }


    }

    public static function napisPorownania($type)
    {
        $date['days'][1] = 'poniedziałek';
        $date['days'][2] = 'wtorek';
        $date['days'][3] = 'środa';
        $date['days'][4] = 'czwartek';
        $date['days'][5] = 'piątek';
        $date['days'][6] = 'sobota';
        $date['days'][7] = 'niedziela';
        $date['months'][1] = 'styczeń';
        $date['months'][2] = 'luty';
        $date['months'][3] = 'marzec';
        $date['months'][4] = 'kwiecień';
        $date['months'][5] = 'maj';
        $date['months'][6] = 'czerwiec';
        $date['months'][7] = 'lipiec';
        $date['months'][8] = 'sierpień';
        $date['months'][9] = 'wrzesień';
        $date['months'][10] = 'październik';
        $date['months'][11] = 'listopad';
        $date['months'][12] = 'grudzień';


        $date['range']['today'] = "dzisiaj";
        $date['range']['yesterday'] = "wczoraj";
        $date['range']['this_week'] = "ten tydzień";
        $date['range']['this_month'] = "ten miesiąc";
        $date['range']['Q1'] = "1 kwartał";
        $date['range']['Q2'] = "2 kwartał";
        $date['range']['Q3'] = "3 kwartał";
        $date['range']['Q4'] = "4 kwartał";
        $date['range']['this_year'] = "ten rok";
        $date['range']['last_week'] = "ostatni tydzień";
        $date['range']['last_month'] = "poprzedni miesiąc";
        $date['range']['custom'] = "od " . @$_POST['dateFrom'] . " do " . @$_POST['dateTo'];


        $monthNum = $_POST['inputZakresDat'];
        $year = $_POST['year_picker'];
        $monthNumvs = $_POST['inputZakresDatvs'];
        $yearvs = $_POST['year_pickervs'];
        $slownie_baza_pusta = "Ten miesiąc";
        $slownie_odniesienie_pusta = "Poprzedni miesiąc";

        $niedodawaj_roku = array(
            'today', 'yesterday', 'this_week', 'this_month', 'last_week'
        );


        if (is_numeric($monthNum)) {
            if (in_array($monthNum, array('Q1', 'Q2', 'Q3', 'Q4'))) {
                $slownie_baza = $date['range'][$monthNum] . ' /' . $year;
            } else {
                $slownie_baza = ucfirst($date['months'][$monthNum]) . " / " . $year;
            }
        } else {
            $slownie_baza = $date['range'][$monthNum];
        }

        if (is_numeric($monthNumvs)) {
            $slownie_odniesienie = ucfirst($date['months'][$monthNumvs]) . " / " . $yearvs;
        } else {
            if (in_array($monthNumvs, array('Q1', 'Q2', 'Q3', 'Q4'))) {
                $slownie_odniesienie = $date['range'][$monthNumvs] . ' / ' . $yearvs;
            } else {
                $slownie_odniesienie = $date['range'][$monthNumvs] . ' / ' . $yearvs;
            }

        }

        if (empty($monthNum)) {
            $slownie_baza = $slownie_baza_pusta;
        }
        if (empty($monthNum)) {
            $slownie_odniesienie = $slownie_odniesienie_pusta;
        }

        if ($type === "same_pola") {
            return array(
                "baza" => $slownie_baza,
                "odniesienie" => $slownie_odniesienie,
            );
        }

        if ($type === "baza") {
            return ($slownie_baza) ? $slownie_baza : "Poprzedni miesiąc" . " " . $yearvs;
        }


        if ($type === "full") {
            return "Zestawienie " . $slownie_baza . " <i class=\"icon-code-tags s-12\"></i> " . $slownie_odniesienie;
        } else {
            // zwracamy odniesienie
            return ($slownie_odniesienie) ? $slownie_odniesienie : "Poprzedni miesiąc" . " " . $yearvs;
        }

    }

    protected function clauseGeneratorLogic($month, $rok, $field = array(), $type = "default")
    {
        $dateclause = "";

        if ($type === 'justdate') {
            if (!empty($month) && is_numeric($month)) {

                if ((isset($month) && $month >= 1 && $month <= 12) &&
                    (isset($rok) && $rok >= 2017 && $rok <= 2050)) {

                    $query_date = $rok . '-' . $month . '-01';
                    $dateclause .= "(";
                    $today = date('Y-m-01', strtotime($query_date));
                    $dateclause .= "DATE('" . $today . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`";
                    $dateclause .= ")";
                }
            } else {
                //DATE('" . $dateclause . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`
                switch ($month) {
                    case "today" :
                        $dateclause .= "(";
                        $today = date('Y-m-d');
                        $dateclause .= "DATE('" . $today . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`";
                        $dateclause .= ")";
                        break;
                    case "yesterday" :
                        $dateclause .= "(";
                        $yesterday = date('Y-m-d', strtotime("-1 days"));
                        $dateclause .= "DATE('" . $yesterday . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`";
                        $dateclause .= ")";
                        break;
                    case "this_month" :
                        $dateclause .= "(";
                        $range = $this->rangeMonth(date('Y-m-d'));
                        $dateclause .= "(DATE('" . $range['start'] . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) AND ";
                        $dateclause .= "(DATE('" . $range['end'] . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) ";
                        $dateclause .= ")";

                        break;
                    case "this_year" :
                        $dateclause .= "(";
                        $fd = date('Y-m-d', strtotime('first day of january this year'));
                        $ld = date('Y-m-d', strtotime('first day of december this year'));
                        $dateclause .= "(DATE('" . $fd . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) AND ";
                        $dateclause .= "(DATE('" . $ld . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) ";
                        $dateclause .= ")";
                        break;
                    case "Q1" :
                        $dateclause .= "(";
                        $fd = date('Y-m-d', strtotime('first day of january this year'));
                        $ld = date('Y-m-d', strtotime('last day of march this year'));
                        $dateclause .= "(DATE('" . $fd . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) AND ";
                        $dateclause .= "(DATE('" . $ld . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) ";
                        $dateclause .= ")";
                        break;
                    case "Q2" :
                        $dateclause .= "(";
                        $fd = date('Y-m-d', strtotime('first day of april this year'));
                        $ld = date('Y-m-d', strtotime('last day of june this year'));
                        $dateclause .= "(DATE('" . $fd . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) AND ";
                        $dateclause .= "(DATE('" . $ld . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) ";
                        $dateclause .= ")";
                        break;
                    case "Q3" :
                        $dateclause .= "(";
                        $fd = date('Y-m-d', strtotime('first day of july this year'));
                        $ld = date('Y-m-d', strtotime('last day of september this year'));
                        $dateclause .= "(DATE('" . $fd . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) AND ";
                        $dateclause .= "(DATE('" . $ld . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) ";
                        $dateclause .= ")";
                        break;
                    case "Q4" :
                        $dateclause .= "(";
                        $fd = date('Y-m-d', strtotime('first day of october this year'));
                        $ld = date('Y-m-d', strtotime('last day of december this year'));
                        $dateclause .= "(DATE('" . $fd . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) AND ";
                        $dateclause .= "(DATE('" . $ld . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) ";
                        $dateclause .= ")";
                        break;
                    case "last_week" :

                        $lw = date('Y-m-d', strtotime("-7 days"));
                        $range = $this->rangeMonth($lw);
                        $dateclause .= "(";
                        $dateclause .= "(DATE('" . $range['start'] . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) AND ";
                        $dateclause .= "(DATE('" . $range['end'] . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) ";
                        $dateclause .= ")";
                        break;
                    case "last_month" :
                        $dateclause .= "(";
                        $fd = date('Y-m-d', strtotime('first day of last month'));
                        $ld = date('Y-m-d', strtotime('last day of last month'));
                        $dateclause .= "(DATE('" . $fd . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) AND ";
                        $dateclause .= "(DATE('" . $ld . "') BETWEEN `data_rozpoczecia` AND `data_zakonczenia`) ";
                        $dateclause .= ")";
                        break;
                }
            }
            if (empty($dateclause)) {
                $dateclause .= $field['defaultClause'];
            }
            return $dateclause;
        }
        if (!empty($month) && is_numeric($month)) {

            if ((isset($month) && $month >= 1 && $month <= 12) &&
                (isset($rok) && $rok >= 2017 && $rok <= 2050)) {

                $query_date = $rok . '-' . $month . '-01';

                $dateclause .= "(";
                $dateclause .= $field['field'] . ' >= "' . date('Y-m-01', strtotime($query_date)) . '" AND ';
                $dateclause .= $field['field'] . ' <= "' . date('Y-m-t', strtotime($query_date)) . '"';
                $dateclause .= ")";

            }
        } else {
            // Zakresem nie jest miesiac
            switch ($month) {
                case "today" :
                    $dateclause .= "(";
                    $today = date('Y-m-d');
                    $dateclause .= $field['field'] . ' = "' . $today . '" ';
                    $dateclause .= ")";
                    break;
                case "yesterday" :
                    $dateclause .= "(";
                    $yesterday = date('Y-m-d', strtotime("-1 days"));
                    $dateclause .= $field['field'] . ' = "' . $yesterday . '" ';
                    $dateclause .= ")";
                    break;
                case "this_month" :
                    $dateclause .= "(";
                    $range = $this->rangeMonth(date('Y-m-d'));
                    $dateclause .= $field['field'] . ' >= "' . $range['start'] . '" AND ';
                    $dateclause .= $field['field'] . ' <= "' . $range['end'] . '"';
                    $dateclause .= ")";

                    break;
                case "this_year" :
                    $dateclause .= "(";
                    $dateclause .= $field['field'] . ' >= "' . date('Y-m-d', strtotime(date('Y-01-01'))) . '"';
                    $dateclause .= ")";
                    break;
                case "Q1" :
                    $dateclause .= "(";
                    $dateclause .= $field['field'] . ' >= "' . date('Y-m-d', strtotime('first day of january this year')) . '" AND ';
                    $dateclause .= $field['field'] . ' <= "' . date('Y-m-d', strtotime('last day of march this year')) . '"';
                    $dateclause .= ")";
                    break;
                case "Q2" :
                    $dateclause .= "(";
                    $dateclause .= $field['field'] . ' >= "' . date('Y-m-d', strtotime('first day of april this year')) . '" AND ';
                    $dateclause .= $field['field'] . ' <= "' . date('Y-m-d', strtotime('last day of june this year')) . '"';
                    $dateclause .= ")";
                    break;
                case "Q3" :
                    $dateclause .= "(";
                    $dateclause .= $field['field'] . ' >= "' . date('Y-m-d', strtotime('first day of july this year')) . '" AND ';
                    $dateclause .= $field['field'] . ' <= "' . date('Y-m-d', strtotime('last day of september this year')) . '"';
                    $dateclause .= ")";
                    break;
                case "Q4" :
                    $dateclause .= "(";
                    $dateclause .= $field['field'] . ' >= "' . date('Y-m-d', strtotime('first day of october this year')) . '" AND ';
                    $dateclause .= $field['field'] . ' <= "' . date('Y-m-d', strtotime('last day of december this year')) . '"';
                    $dateclause .= ")";
                    break;
                case "last_week" :
                    $dateclause .= "(";
                    $lw = date('Y-m-d', strtotime("-7 days"));
                    $range = $this->rangeMonth($lw);
                    $dateclause .= $field['field'] . ' >= "' . $range['start'] . '" AND ';
                    $dateclause .= $field['field'] . ' <= "' . $range['end'] . '"';
                    $dateclause .= ")";
                    break;
                case "last_month" :
                    $dateclause .= "(";
                    $dateclause .= $field['field'] . ' >= "' . date('Y-m-d', strtotime('first day of last month')) . '" AND ';
                    $dateclause .= $field['field'] . ' <= "' . date('Y-m-d', strtotime('last day of last month')) . '"';
                    $dateclause .= ")";
                    break;
                case "custom" :
                    // todo custom
                    $sd = $this->input->post("dateFrom");
                    $fd = $this->input->post("dateTo");
                    if (!empty($sd) && !empty($fd)) {
                        $dateclause .= "(";
                        $dateclause .= $field['field'] . ' >= "' . $sd . '" AND ';
                        $dateclause .= $field['field'] . ' <= "' . $fd . '"';
                        $dateclause .= ")";
                    }
                    break;
            }
        }


        if (empty($dateclause)) {
            $dateclause .= $field['defaultClause'];
        }
        return $dateclause;
    }


    protected function procedureDateGenerator($month, $rok, $type = "default")
    {
        /*
            Domyslnie ten i poprzedni miesiac
        */
        $range = $this->rangeMonth(date('Y-m-d'));
        if ($type === "vs") {
            // Default vs - mesiac temu
            $dstart = date('Y-m-d', strtotime('first day of last month'));
            $dend = date('Y-m-d', strtotime('last day of last month'));
        } else {
            // Default baza

            $dstart = $range['start'];
            $dend = $range['end'];
        }

        if (!empty($month) && is_numeric($month)) {

            if ((isset($month) && $month >= 1 && $month <= 12) &&
                (isset($rok) && $rok >= 2017 && $rok <= 2050)) {


                $query_date = $rok . '-' . $month . '-01';
                $dstart = date('Y-m-01', strtotime($query_date));
                $dend = date('Y-m-t', strtotime($query_date));

            }
        } else {
            // Zakresem nie jest miesiac
            switch ($month) {
                case "today" :
                    $today = date('Y-m-d');
                    $dstart = $today;
                    $dend = $today;
                    break;
                case "yesterday" :
                    $yesterday = date('Y-m-d', strtotime("-1 days"));
                    $dstart = $yesterday;
                    $dend = $yesterday;
                    break;
                case "this_month" :
                    $dstart = $range['start'];
                    $dend = $range['end'];
                    break;
                case "this_week" :
                    $monday = strtotime("last monday");
                    $monday = date('w', $monday) == date('w') ? $monday + 7 * 86400 : $monday;
                    $sunday = strtotime(date("Y-m-d", $monday) . " +6 days");
                    $dstart = date("Y-m-d", $monday);
                    $dend = date("Y-m-d", $sunday);
                    break;
                case "this_year" :
                    $dstart = date('Y-m-d', strtotime(date('Y-01-01')));
                    $dend = date('Y-m-d', strtotime('last day of december this year'));
                    break;
                case "Q1" :
                    $dstart = date('Y-m-d', strtotime('first day of january this year'));
                    $dend = date('Y-m-d', strtotime('last day of march this year'));
                    break;
                case "Q2" :
                    $dstart = date('Y-m-d', strtotime('first day of april this year'));
                    $dend = date('Y-m-d', strtotime('last day of june this year'));
                    break;
                case "Q3" :
                    $dstart = date('Y-m-d', strtotime('first day of july this year'));
                    $dend = date('Y-m-d', strtotime('last day of september this year'));
                    break;
                case "Q4" :
                    $dstart = date('Y-m-d', strtotime('first day of october this year'));
                    $dend = date('Y-m-d', strtotime('last day of december this year'));
                    break;
                case "last_week" :
                    $monday = strtotime("last monday");
                    $monday = date('W', $monday) == date('W') ? $monday - 7 * 86400 : $monday;
                    $sunday = strtotime(date("Y-m-d", $monday) . " +6 days");
                    $dstart = date("Y-m-d", $monday);
                    $dend = date("Y-m-d", $sunday);
                    break;
                case "last_month" :
                    $dstart = date('Y-m-d', strtotime('first day of last month'));
                    $dend = date('Y-m-d', strtotime('last day of last month'));
                    break;
                case "custom" :
                    // todo custom
                    $sd = $this->input->post("dateFrom");
                    $fd = $this->input->post("dateTo");
                    if (!empty($sd) && !empty($fd)) {
                        $dstart = $sd;
                        $dend = $fd;
                    }
                    break;
            }
        }

        return array('dstart' => $dstart, 'dend' => $dend);
    }

    public function pojazdy()
    {

        $range = $this->procedureDateGenerator($this->input->post('inputZakresDat'), $this->input->post('year_picker'));

        $query2 = $this->db->query("CALL `pojazdy_analiza`('" . $range['dstart'] . "', '" . $range['dend'] . "');");
        $a2 = $query2->result_array();


        $rangevs = $this->procedureDateGenerator($this->input->post('inputZakresDatvs'), $this->input->post('year_pickervs'), 'vs');

        $query2vs = $this->db->query("CALL `pojazdy_analiza`('" . $rangevs['dstart'] . "', '" . $rangevs['dend'] . "');");
        $a2vs = $query2vs->result_array();


        /*
         *  [0 vs]=>
              array(9) {
                ["poj_id"]=>
                string(1) "1"
                ["nr_rej"]=>
                string(7) "PO164RN"
                ["org_przebieg"]=>
                string(6) "256347"
                ["aktualny_przebieg"]=>
                NULL
                ["wartosc_pojazdu"]=>
                string(5) "65.00"
                ["koszty_pojazdu"]=>
                NULL
                ["litry"]=>
                NULL
                ["koszt_km"]=>
                NULL
                ["przejechane_kl"]=>
                NULL
              }
         */
        foreach ($a2vs as $vs) {
            $vsarray[$vs['poj_id']] = array(
                "aktualny_przebieg" => $vs['aktualny_przebieg'],
                "koszty_pojazdu" => $vs['koszty_pojazdu'],
                "litry" => $vs['litry'],
                "koszt_km" => $vs['koszt_km'],
                "przejechane_kl" => $vs['przejechane_kl'],
            );
        }

        return array("baza" => $a2, "odniesienie" => $vsarray);

    }

    protected function clauseGenerator($start, $end, $fields = array(), $type = "default")
    {
        /*
         * Zmienne
         * Data bazowa
       */
        $rodzajzakresu = $this->input->post($start);
        $rok = $this->input->post('year_picker');
        $dateFrom = $this->input->post('dateFrom');
        $dateTo = $this->input->post('dateTo');

        /*
        * Zmienne
        * Data porownawcza
       */
        $rodzajzakresuvs = $this->input->post($end);
        $rokvs = $this->input->post('year_pickervs');
        $dateFromvs = $this->input->post('dateFromvs');
        $dateTovs = $this->input->post('dateTovs');

        $dateclause = "";
        $endclause = "";

        $bazaclause = $this->clauseGeneratorLogic(
            $rodzajzakresu, $rok,
            array(
                'zakresOd' => $dateFrom,
                'zakresDo' => $dateTo,
                'field' => $fields[0],
                'defaultClause' => $fields[1]
            ), $type);

        $endclause = $this->clauseGeneratorLogic(
            $rodzajzakresuvs, $rokvs,
            array(
                'zakresOd' => $dateFromvs,
                'zakresDo' => $dateTovs,
                'field' => $fields[0],
                'defaultClause' => $fields[1]
            ), $type);

        return array('start' => $bazaclause, 'end' => $endclause);
    }

    public function s_wydatki_rejony()
    {
        $range = $this->procedureDateGenerator($this->input->post('inputZakresDat'), $this->input->post('year_picker'));
        $rangevs = $this->procedureDateGenerator($this->input->post('inputZakresDatvs'), $this->input->post('year_pickervs'), 'vs');

        $query2 = $this->db->query("CALL `wydatki_analiza_vs`('" . $range['dstart'] . "', '" . $range['dend'] . "','" . $rangevs['dstart'] . "', '" . $rangevs['dend'] . "');");

        $a2 = $query2->result_array();
        return $a2;

    }

    /*
     * Procedura statystyka wydatkow z podzialem na kategorie
     */
    public function s_wydatki()
    {

        $range = $this->procedureDateGenerator($this->input->post('inputZakresDat'), $this->input->post('year_picker'));
        $rangevs = $this->procedureDateGenerator($this->input->post('inputZakresDatvs'), $this->input->post('year_pickervs'), 'vs');
        $query2 = $this->db->query("CALL `wydatki_analiza_kategorie_vs`('" . $range['dstart'] . "', '" . $range['dend'] . "','" . $rangevs['dstart'] . "', '" . $rangevs['dend'] . "');");

        $a2 = $query2->result_array();
        return $a2;
    }

    public function s_ostatnie_przychody_ten_miesiac()
    {


        $query = $this->db->query("
             SELECT wartosc as kwota_brutto,z_dnia as data_zakupu,numer as dokument,id_przychodu as id_wydatku
                FROM `przychody` 
                 LEFT JOIN przychody_platnosci ON przychody.id_przychodu = przychody_platnosci.fk_przychodu
                 WHERE przychody_platnosci.status != 2 AND datediff(`przychody`.`termin_platnosci`,NOW()) < 0  
ORDER BY `przychody`.`termin_platnosci`  ASC
            ");
        return $query->result_array();


    }

    public function s_Przychody_skalaroku()
    {
        $query = $this->db->query("
        SELECT 
COALESCE(SUM(case when month(`z_dnia`)=1 then netto end),0) As Styczeń, 
COALESCE(SUM(case when month(`z_dnia`)=2 then netto end),0) As Luty, 
COALESCE(SUM(case when month(`z_dnia`)=3 then netto end),0) As Marzec, 
COALESCE(SUM(case when month(`z_dnia`)=4 then netto end),0) As Kwieceń, 
COALESCE(SUM(case when month(`z_dnia`)=5 then netto end),0) As Maj, 
COALESCE(SUM(case when month(`z_dnia`)=6 then netto end),0) As Czerwiec, 
COALESCE(SUM(case when month(`z_dnia`)=7 then netto end),0) As Lipiec,
COALESCE(SUM(case when month(`z_dnia`)=8 then netto end),0) As Sierpień, 
COALESCE(SUM(case when month(`z_dnia`)=9 then netto end),0) As Wrzesień, 
COALESCE(SUM(case when month(`z_dnia`)=10 then netto end),0) As Październik, 
COALESCE(SUM(case when month(`z_dnia`)=11 then netto end),0) As Listopad, 
COALESCE(SUM(case when month(`z_dnia`)=12 then netto end),0) As Grudzień 
FROM `przychody` WHERE YEAR(z_dnia) = YEAR (CURDATE()) GROUP BY YEAR(z_dnia)
        ");


        return $query->result_array();
    }


    public function s_wydatki_kat_skalaroku()
    {
        $query = $this->db->query("SELECT 
wydatki_kategorie.nazwa,
COALESCE(SUM(case when month(`data_zakupu`)=1 then kwota_netto end),0) As Styczeń, 
COALESCE(SUM(case when month(`data_zakupu`)=2 then kwota_netto end),0) As Luty, 
COALESCE(SUM(case when month(`data_zakupu`)=3 then kwota_netto end),0) As Marzec, 
COALESCE(SUM(case when month(`data_zakupu`)=4 then kwota_netto end),0) As Kwieceń, 
COALESCE(SUM(case when month(`data_zakupu`)=5 then kwota_netto end),0) As Maj, 
COALESCE(SUM(case when month(`data_zakupu`)=6 then kwota_netto end),0) As Czerwiec, 
COALESCE(SUM(case when month(`data_zakupu`)=7 then kwota_netto end),0) As Lipiec,
COALESCE(SUM(case when month(`data_zakupu`)=8 then kwota_netto end),0) As Sierpień, 
COALESCE(SUM(case when month(`data_zakupu`)=9 then kwota_netto end),0) As Wrzesień, 
COALESCE(SUM(case when month(`data_zakupu`)=10 then kwota_netto end),0) As Październik, 
COALESCE(SUM(case when month(`data_zakupu`)=11 then kwota_netto end),0) As Listopad, 
COALESCE(SUM(case when month(`data_zakupu`)=12 then kwota_netto end),0) As Grudzień 
FROM `wydatki` 
LEFT JOIN wydatki_kategorie ON wydatki.kategoria = wydatki_kategorie.id_kat
WHERE YEAR(data_zakupu) = YEAR (CURDATE()) GROUP BY YEAR(data_zakupu), wydatki.kategoria");
        return $query->result_array();
    }

    public function s_wydatki_skalaroku()
    {

        $query = $this->db->query("SELECT 
COALESCE(SUM(case when month(`data_zakupu`)=1 then kwota_netto end),0) As Styczeń, 
COALESCE(SUM(case when month(`data_zakupu`)=2 then kwota_netto end),0) As Luty, 
COALESCE(SUM(case when month(`data_zakupu`)=3 then kwota_netto end),0) As Marzec, 
COALESCE(SUM(case when month(`data_zakupu`)=4 then kwota_netto end),0) As Kwieceń, 
COALESCE(SUM(case when month(`data_zakupu`)=5 then kwota_netto end),0) As Maj, 
COALESCE(SUM(case when month(`data_zakupu`)=6 then kwota_netto end),0) As Czerwiec, 
COALESCE(SUM(case when month(`data_zakupu`)=7 then kwota_netto end),0) As Lipiec,
COALESCE(SUM(case when month(`data_zakupu`)=8 then kwota_netto end),0) As Sierpień, 
COALESCE(SUM(case when month(`data_zakupu`)=9 then kwota_netto end),0) As Wrzesień, 
COALESCE(SUM(case when month(`data_zakupu`)=10 then kwota_netto end),0) As Październik, 
COALESCE(SUM(case when month(`data_zakupu`)=11 then kwota_netto end),0) As Listopad, 
COALESCE(SUM(case when month(`data_zakupu`)=12 then kwota_netto end),0) As Grudzień 
FROM `wydatki` WHERE YEAR(data_zakupu) = YEAR (CURDATE()) GROUP BY YEAR(data_zakupu)
     ");
        return $query->result_array();
    }

    public function s_ostatnie_wydatki_ten_miesiac()
    {

        $query = $this->db->query("
                SELECT kwota_brutto,kwota_netto,data_zakupu,dokument,id_wydatku 
                FROM `wydatki` 
                LEFT JOIN platnosci ON wydatki.id_wydatku = platnosci.fk_wydatek
                WHERE platnosci.status != 2
                ORDER BY `wydatki`.`data_zakupu`  DESC
                
            ");
        return $query->result_array();


    }

    /*
     * VS sredni czas z calego roku do BAZY
     */
    public function s_wydatki_sredniczasplacenia()
    {

        $range = $this->procedureDateGenerator($this->input->post('inputZakresDat'), $this->input->post('year_picker'));
        $query2 = $this->db->query("CALL `wydatki_sredniczasplacenia_vs`('" . $range['dstart'] . "', '" . $range['dend'] . "');");
        $a2 = $query2->result_array();
        return $a2;
    }

    public function s_przychody_sredniczasplacenia()
    {

        $range = $this->procedureDateGenerator($this->input->post('inputZakresDat'), $this->input->post('year_picker'));
        $query2 = $this->db->query("CALL `przychody_sredniczasplacenia_vs`('" . $range['dstart'] . "', '" . $range['dend'] . "');");
        $a2 = $query2->result_array();
        return $a2;

    }

    public function s_przychody_rejon()
    {

        $range = $this->procedureDateGenerator($this->input->post('inputZakresDat'), $this->input->post('year_picker'));
        $rangevs = $this->procedureDateGenerator($this->input->post('inputZakresDatvs'), $this->input->post('year_pickervs'), 'vs');
        $query2 = $this->db->query("CALL `s_przychody_analiza_rejon_vs`('" . $range['dstart'] . "', '" . $range['dend'] . "','" . $rangevs['dstart'] . "', '" . $rangevs['dend'] . "');");

        $a2 = $query2->result_array();
        return $a2;

    }

    public function s_przychody_wkres_lin()
    {

        try {

            $query1 = $this->db->query("CALL `s_przychody_wykres_zakres`();");
            $a1 = $query1->result_array();
            $this->db->close();


        } catch (Exception $e) {
            echo $e->getMessage();
        }

        try {


            $query3 = $this->db->query("CALL s_przychody_wykres_zakres_faktyczne();");
            $a3 = $query3->result_array();

            $this->db->close();


        } catch (Exception $e) {
            echo $e->getMessage();
        }

        try {


            $query2 = $this->db->query("CALL s_przychody_wykres_bank();");
            $a2 = $query2->result_array();

            $this->db->close();


        } catch (Exception $e) {
            echo $e->getMessage();
        }


        return array('caly_rok' => $a1, 'faktyczne' => $a3, 'bank' => $a2);
    }


    public function przychody_data_platnosci_dziennie_tenrok()
    {
        $query = $this->db->query("SELECT termin_platnosci,COALESCE(SUM(`wartosc`)) as kwota
            FROM `przychody` WHERE YEAR(termin_platnosci) = YEAR (CURDATE())
            GROUP BY termin_platnosci
            ORDER BY termin_platnosci ASC");
        $a1 = $query->result_array();
        $return = array('data' => array());

        if (!empty($a1)) {
            foreach ($a1 as $wo) {
                $date = strtotime($wo['termin_platnosci']) * 1000;
                $return['data'][] = array($date, $wo['kwota']);
            }
        }

        return $return;
    }

    /*
     * Start FCF2
     */


    public function FCF2()
    {
        try {


            $query3 = $this->db->query("CALL s_przychody_wykres_zakres();");
            $a3 = $query3->result_array();

            $this->db->close();


        } catch (Exception $e) {
            echo $e->getMessage();
        }

        $total = 0;
        if (!empty($a3)) {
            foreach ($a3 as $v) {
                foreach ($v as $k => $w) {
                    $total += $w;
                }

            }
        }
        $return = array('przychody' => $a3, 'suma' => $total);
        return $return;
    }

    public function przelewy_dziennie_tenrok()
    {
        try {


            $query3 = $this->db->query("CALL s_przychody_wykres_bank_dziennie();");
            $a3 = $query3->result_array();

            $this->db->close();


        } catch (Exception $e) {
            echo $e->getMessage();
        }
        try {


            $query4 = $this->db->query("CALL s_przychody_wykres_bank();");
            $as = $query4->result_array();

            $this->db->close();


        } catch (Exception $e) {
            echo $e->getMessage();
        }
        $return = array('data' => array());

        $total = 0;

        if (!empty($a3)) {
            foreach ($a3 as $wo) {
                $date = strtotime($wo['data_waluty']) * 1000;
                $total += $wo['przelew'];
                $return['data'][] = array($date, $wo['przelew']);
            }
        }

        return array('return' => $return, 'miesiecznie' => $as[0], 'suma' => $total);

    }

    /*
     * End FCF2
     */
    public function s_przychody_klient()
    {
        $range = $this->procedureDateGenerator($this->input->post('inputZakresDat'), $this->input->post('year_picker'));
        $rangevs = $this->procedureDateGenerator($this->input->post('inputZakresDatvs'), $this->input->post('year_pickervs'), 'vs');
        $query2 = $this->db->query("CALL `s_przychody_klient_analiza_vs`('" . $range['dstart'] . "', '" . $range['dend'] . "','" . $rangevs['dstart'] . "', '" . $rangevs['dend'] . "');");

        $a2 = $query2->result_array();
        return $a2;
    }


    public function s_Wydatki_raport_caly()
    {


        $defstartclause = 'wydatki.data_zakupu >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH';
        $clausebaza = $this->clauseGenerator('inputZakresDat', 'inputZakresDat', array('wydatki.data_zakupu', $defstartclause));
        $dateclause = $clausebaza['start'];


        $query = $this->db->query("
          SELECT  sum(kwota_brutto) as kwota, id_kupujacy
                FROM `wydatki`
                WHERE   
                    " . $dateclause . "
              
                GROUP BY `id_kupujacy`
        ");


        $o = $query->result();
        foreach ($o as $res) {
            $re[$res->id_kupujacy] =
                array(
                    "kwota" => $res->kwota,
                );
        }

        return $re;
    }

    public function s_Umowy_raport_caly()
    {
        // Bazowy miesiac

        $defstartclause = date("Y-m-d");
        $clausebaza = $this->clauseGenerator('inputZakresDat', 'inputZakresDat', array('wydatki.data_zakupu', $defstartclause), 'justdate');
        $dateclause = $clausebaza['start'];

        $query = $this->db->query("
          
                SELECT data_rozpoczecia,
                data_zakonczenia,
                coalesce(sum(do_wyplaty),0) as kwota,
                 coalesce(sum(zus_pracownik),0) as zus_pracownik,
                  coalesce(sum(zus_pracodawca),0) as zus_pracodawca,
                   `fk_pracownik`
                FROM `pracownik_umowy`
                WHERE   
                    " . $dateclause . "
                 
                GROUP BY `fk_pracownik`
        ");


        $re = array();
        foreach ($query->result() as $res) {
            //$re[$res["fk_prac"]]["Kwota"][] = $res["kwota"];
            $re[$res->fk_pracownik] =
                array(
                    //"prac" =>$res->fk_prac,
                    "kwota" => $res->kwota,
                    "zus_pracownik" => $res->zus_pracownik,
                    "zus_pracodawca" => $res->zus_pracodawca
                );
        }

        return $re;

    }


    public function s_pobierzKodekPracy($data = null)
    {
        $this->db->select("godzin");


        if ((isset($_POST['inputZakresDat']) && $_POST['inputZakresDat'] >= 1 && $_POST['inputZakresDat'] <= 12) &&
            (isset($_POST['year_picker']) && $_POST['year_picker'] >= 2017 && $_POST['year_picker'] <= 2050)) {

            $query_date = $_POST['year_picker'] . '-' . $_POST['inputZakresDat'] . '-01';

            $m = date('m', strtotime($query_date));
            $y = date('Y', strtotime($query_date));

        } else {
            $m = date('m');
            $y = date('Y');
        }


        $this->db->where('miesiac =', $m);
        $this->db->from("kp_" . $y);
        $query = $this->db->get()->result();


        return $query;
    }

    public function s_Potracenia_raport_caly()
    {
        $defstartclause = 'kiedy >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH';
        $clausebaza = $this->clauseGenerator('inputZakresDat', 'inputZakresDat', array('kiedy', $defstartclause));
        $dateclause = $clausebaza['start'];

        $query = $this->db->query("
            SELECT sum(kwota) as kwota,
            fk_pracownik
            FROM pracownik_potracenia
            WHERE " . $dateclause . "
            
            GROUP BY fk_pracownik
        ");

        $re = array();
        foreach ($query as $res) {

            $re[$res->fk_pracownik] =
                array(
                    "kwota" => $res->kwota,
                );
        }
        return $re;
    }


    public function s_Udzial_w_przychodzie_caly()
    {
        $this->db->select("przychody_udzialy.fk_pracownik,sum(round(przychody_udzialy.udzial * przychody.netto) / 100) as kwne");
        $this->db->join("przychody", "przychody_udzialy.fk_przychodu = przychody.id_przychodu", 'left');

        if ((isset($_POST['inputZakresDat']) && $_POST['inputZakresDat'] >= 1 && $_POST['inputZakresDat'] <= 12) &&
            (isset($_POST['year_picker']) && $_POST['year_picker'] >= 2017 && $_POST['year_picker'] <= 2050)) {

            $query_date = $_POST['year_picker'] . '-' . $_POST['inputZakresDat'] . '-01';
            $dstart = date('Y-m-01', strtotime($query_date));
            $dend = date('Y-m-t', strtotime($query_date));
            $this->db->group_start();
            $this->db->where('przychody.z_dnia >=', $dstart);
            $this->db->where('przychody.z_dnia <=', $dend);
            $this->db->group_end();

        } else {
            $range = $this->rangeMonth(date('Y-m-d'));
            $dstart = $range['start'];
            $dend = $range['end'];
            $this->db->group_start();
            $this->db->where('przychody.z_dnia >=', $dstart);
            $this->db->where('przychody.z_dnia <=', $dend);
            $this->db->group_end();
        }


        $this->db->group_by('przychody_udzialy.fk_pracownik');
        $this->db->from("przychody_udzialy");
        $query = $this->db->get()->result();
        $re = array();
        foreach ($query as $res) {
            $re[$res->fk_pracownik] =
                array(
                    "kwne" => $res->kwne,
                );
        }

        return $re;

    }

    public function s_pracownicy($user = null)
    {

        $range = $this->procedureDateGenerator($this->input->post('inputZakresDat'), $this->input->post('year_picker'));

        if ($user) {
            $range = $this->procedureDateGenerator($this->input->post('customMonth'), $this->input->post('customYear'));
        }

        /*
         * Zrobic where w pracowniku na place/umowy jak jest w procedurze
         *
           ["id_pracownika"]=>
            string(1) "1"
            ["name"]=>
            string(18) "Szymon Białkowski"
            ["staz_"]=>
            string(2) "21"
            ["udzialy_kwota"]=>
            string(7) "9879.96"
            ["to_hand_cost"]=>
            string(4) "0.00"
            ["delegation_cost"]=>
            string(4) "0.00"
            ["deduction_cost"]=>
            string(4) "0.00"
            ["extra_cost"]=>
            string(4) "0.00"
            ["expense_cost"]=>
            string(4) "0.00"
            ["p_am"]=>
            string(7) "2000.00"
            ["a_am"]=>
            NULL
            ["incost"]=>
            string(6) "666.40"
            ["a_incost"]=>
            NULL
         */
        try {
            $kodeks = $this->s_pobierzKodekPracy();
            if ($user) {
                $query = $this->db->query("CALL pracownicy_analiza_s_whereP('" . $range['dstart'] . "', '" . $range['dend'] . "','" . (int)$user . "');");
            } else {
                $query = $this->db->query("CALL pracownicy_analiza_s('" . $range['dstart'] . "', '" . $range['dend'] . "');");
            }

            /* $queryvs = $this->db->query("CALL pracownicy_analiza_s('" . $dstartvs . "', '" . $dendvs . "');");*/
            $q = $query->result_array();
            /* $qvs = $queryvs->result_array(); */
        } catch (Exception $e) {

        }

        /*
                $outvs = array();
                $kpvs = 0;
                foreach ($qvs as $tvs) {
                    $seg1 = bcadd($tvs['to_hand_cost'],
                        bcadd(
                            bcadd($tvs['p_am'], $tvs['extra_cost'], 2),
                            bcadd($tvs['a_am'], $tvs['delegation_cost'], 2)
                            , 2
                        ), 2
                    );
                    $seg2 = bcsub(
                        $seg1,
                        $tvs['deduction_cost'],
                        2
                    );
                    $ubezpieczenia = bcadd($tvs['incost'], $tvs['a_incost'], 2);
                    $kpvs = bcadd($kpvs, bcadd($seg2, $ubezpieczenia, 2), 2);

                }
        */
        $out = array();
        $kp = 0;
        $suma_przychodu = 0;


        // calculation loop
        foreach ($q as $c) {
            $suma_przychodu = bcadd($suma_przychodu, $c['udzialy_kwota'], 2);
        }

        foreach ($q as $t) {
            $seg1 = bcadd($t['to_hand_cost'],
                bcadd(
                    bcadd($t['p_am'], $t['extra_cost'], 2),
                    bcadd($t['a_am'], $t['delegation_cost'], 2)
                    , 2
                ), 2
            );
            $seg2 = bcsub(
                $seg1,
                $t['deduction_cost'],
                2
            );
            $ubezpieczenia = bcadd($t['incost'], $t['a_incost'], 2);
            $kp = bcadd($seg2, $ubezpieczenia, 2);


            $out[$t['id_pracownika']] = array(
                "id_pracownika" => $t['id_pracownika'],
                "name" => $t['name'],
                "staz_" => $t['staz_'],
                "udzialy_kwota" => $t['udzialy_kwota'],
                "to_hand_cost" => $t['to_hand_cost'],
                "delegation_cost" => $t['delegation_cost'],
                "deduction_cost" => $t['deduction_cost'],
                "extra_cost" => $t['extra_cost'],
                "expense_cost" => $t['expense_cost'],
                "p_am" => $t['p_am'], //place kwota
                "a_am" => $t['a_am'], // umowy kwota
                "incost" => $t['incost'],
                "a_incost" => $t['a_incost'],
                "koszty_pracodawcy" => $kp,
                "koszt_godziny" => bcdiv($kp, $kodeks[0]->godzin, 2)
            );
            $kp = 0;
        }

        return array('wyniki' => $out, 'obliczenia' => array('suma_przychodu' => $suma_przychodu), 'kp' => $kodeks[0]->godzin);

    }

    public function FCF_pracownik()
    {

        $query2 = $this->db->query("CALL `s_fcf_pracownicy_suma`();");
        $a2 = $query2->result_array();

        return array("wartosci" => $a2);
    }

    public function Wydatki_faktury_statystyka()
    {

        $query2 = $this->db->query("SELECT kontrahenci.nazwa,sum(kwota_brutto) as kwota, count(id_wydatku) as ilosc FROM `wydatki` 
        LEFT JOIN kontrahenci ON wydatki.kontrahent = kontrahenci.id_kontrahenta
        GROUP BY `kontrahent` 
        HAVING ilosc > 3
        ORDER BY `ilosc` DESC");
        $a2 = $query2->result_array();

        return $a2;
    }



    public function Pojazdy_koszty_skalaroku()
    {

        $query2 = $this->db->query("CALL `pojazdy_koszty_skalaroku`();");
        $a2 = $query2->result_array();

        return $a2;
    }

    public function FCF_korekty($default = "Wydatki")
    {
        $field = "";
        if ($default === "Wydatki") {
            $extra = "AND type='0'";
        } elseif ($default === "Przychody") {
            $extra = "AND type='1'";
        } else {
            $extra = "";
            $field = "type,";
        }
        $q1 = $this->db->query("SELECT
             month," . $field . "
             COALESCE(SUM(case when `method`= 'add' then value end),0) As dodawanie,
             COALESCE(SUM(case when `method`= 'sub' then value end),0) As odejmowanie,
             (COALESCE(SUM(case when `method`= 'add' then value end),0) - COALESCE(SUM(case when `method`= 'sub' then value end),0)) as final,
             MAX((case when `method`= 'percent' then value end)) as percent
             FROM forecast_correction
             WHERE year = YEAR(CURDATE()) " . $extra . "
             GROUP BY month,year;");
        $r1 = $q1->result_array();
        return $r1;

    }

    public function custom_decimal($decimal)
    {
        $decimal = str_replace("Zł ", "", $decimal);
        $decimal = str_replace("dm3 ", "", $decimal);
        $decimal = str_replace(",", "", $decimal);


        if (preg_match('/^[0-9]+\.[0-9]{2}$/', $decimal) || is_numeric($decimal)) {
            return $decimal;
        } else {
            return FALSE;
        }
    }

    public function FCF_korekta_add()
    {

        if (!$this->input->is_ajax_request()) {
            exit('No direct script access allowed');
        }


        $status = FALSE;
        $message = "";
        $reponse = array(
            'csrfName' => $this->security->get_csrf_token_name(),
            'csrfHash' => $this->security->get_csrf_hash()
        );

        $this->load->helper(array('form', 'url'));

        $this->load->library('form_validation');

        $this->form_validation->set_rules('inputZakresDat', 'numer konta', 'required|trim|alpha_numeric', array(
                'alpha_numeric' => "Miesiąc może  składać się tylko z cyfr",
                'required' => "Musisz podać Miesiąc",
            )
        );
        $this->form_validation->set_rules('inputOpis', 'opis', 'required|trim|min_length[3]|max_length[249]', array(
                'alpha_numeric' => "Miesiąc może  składać się tylko z cyfr",
                'min_length' => "Opis musi mieć conajmniej 3 znaki",
                'max_length' => "Opis może mieć najwyżej 249 znaków",
            )
        );

        $inputZakresDat = $this->input->post("inputZakresDat");
        $inputMetoda = $this->input->post("inputMetoda");
        $inputRodzaj = $this->input->post("inputRodzaj");
        $inputWartosc = $this->input->post("inputWartosc");
        $inputOpis = $this->input->post("inputOpis");

        $obliczProcent = null; // domyslnie nie zmieniamy

        if (empty($inputMetoda) || !in_array($inputMetoda, array('add', 'sub', 'percent'))) {
            $message = "Nie wybrano pola korekty";
        }

        if (!in_array($inputRodzaj, array(0, 1))) {
            $message = "Nie wybrano pola korekty";
        }

        if ($inputZakresDat <= 1 && $inputZakresDat >= 12) {
            $message = "Nieprawidłowy miesiąc";
        }

        if (empty($this->custom_decimal($inputWartosc)) || strlen($this->custom_decimal($inputWartosc)) < 0 || $this->custom_decimal($inputWartosc) === "0.00") {
            $message = "Podaj prawidłową wartość";
        }
        if (empty($inputWartosc)) {
            $message = "Nie podano wartości korekty";
        } else {
            if ($inputMetoda === "percent") {
                if ($inputWartosc >= 100 || $inputWartosc <= 0) {
                    $message = "Nieprawidłowa wartość procentowa";
                }
                $obliczProcent = ($inputWartosc / 100) + 1;

            } else {

                $obliczProcent = $inputWartosc;
            }
        }


        if ($this->form_validation->run() == FALSE) {
            $message = validation_errors();
        } else {

            // Jeżeli jest błąd w adresie pokaż

            try {
                $this->db->trans_begin();
                $post_data = array(
                    'method' => $inputMetoda,
                    'month' => $inputZakresDat,
                    'year' => date('Y'), // do ogarniecia
                    'value' => $obliczProcent,
                    'type' => $inputRodzaj,
                    'opis' => $inputOpis,
                );

                $this->db->insert('forecast_correction', $post_data);
                $personID = $this->db->insert_id();


                if ($this->db->trans_status() === FALSE || strlen($message) > 0 || !is_numeric($personID)) {
                    $this->db->trans_rollback();
                } else {
                    $this->db->trans_commit();
                    if (isset($personID) && is_numeric($personID)) {
                        $status = TRUE;
                        $message = "Dodano korektę";
                    }
                }
            } catch (Exception $e) {
                $this->db->trans_rollback();
                log_message('error', sprintf('%s : %s : DB transaction failed. Error no: %s, Error msg:%s, Last query: %s', __CLASS__, __FUNCTION__, $e->getCode(), $e->getMessage(), print_r($this->main_db->last_query(), TRUE)));
            }


        }

        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("regen" => $reponse, "response" => array("status" => $status, "message" => $message))));
    }

    public function fcf_korekty_lista()
    {
        $r1 = $this->FCF_korekty('Wydatki');
        $r2 = $this->FCF_korekty('Przychody');

        $warray = array_fill(1, 12, '0');
        $parray = array_fill(1, 12, '0');


        foreach ($r1 as $k) {
            $p = (empty($k['percent'])) ? '' : ' ,' . $k['percent'] . ' %';
            if ($k['final'] > 0) {
                $wynik = "+ " . $k['final'] . " " . $p;
            } elseif ($k['final'] < 0) {
                $wynik = $k['final'];
            } elseif ($k['final'] == 0) {
                $wynik = "% " . $k['percent'];
            }
            $warray[$k['month']] = $wynik;

        }

        foreach ($r2 as $k) {
            $p = (empty($k['percent'])) ? '' : ' ,' . $k['percent'] . ' %';
            if ($k['final'] > 0) {
                $wynik = "+ " . $k['final'] . " " . $p;
            } elseif ($k['final'] < 0) {
                $wynik = $k['final'];
            } elseif ($k['final'] == 0) {
                $wynik = "% " . $k['percent'];
            }
            $parray[$k['month']] = $wynik;

        }
        return array("wydatki" => $warray, "przychody" => $parray);

    }

    public function Zestawienie_monitow()
    {
        $q1 = $this->db->query("SELECT nazwa,sum(pozostala_kwota) as zalegla_kwota,COUNT(id_platnosci) as ilosc,fk_kontrahent FROM `przychody_platnosci`
        JOIN przychody ON przychody_platnosci.fk_przychodu = przychody.id_przychodu
        JOIN kontrahenci ON przychody.fk_kontrahent = kontrahenci.id_kontrahenta
        WHERE pozostala_kwota > 0 AND datediff(`przychody`.`termin_platnosci`,NOW()) < 0
        GROUP BY fk_kontrahent;");
        $r1 = $q1->result_array();
        return $r1;
    }

    public function FCF_przychodu()
    {

        $srednia_przychodow_q = $this->db->query("SELECT AVG(avg_cost) as srednia
                            FROM
                            (
                                SELECT month(z_dnia), SUM(t.netto) AS avg_cost
                                FROM przychody t
                              WHERE `z_dnia` >= last_day(now()) + interval 1 day - interval 3 month
                               GROUP BY month(z_dnia)
                            ) as inner_query;");

        $srednia_przychodow = $srednia_przychodow_q->row();

        $base_month_cost = $srednia_przychodow->srednia;
        $default_percent = 1.03;

        $q1 = $this->db->query("SELECT
             month,
             COALESCE(SUM(case when `method`= 'add' then value end),0) As dodawanie,
             COALESCE(SUM(case when `method`= 'sub' then value end),0) As odejmowanie,
             (COALESCE(SUM(case when `method`= 'add' then value end),0) - COALESCE(SUM(case when `method`= 'sub' then value end),0)) as final,
             MAX((case when `method`= 'percent' then value end)) as percent
             FROM forecast_correction
             WHERE year = YEAR(CURDATE()) + 1 AND type = '1'
             GROUP BY month,year;");
        $r1 = $q1->result_array();


        for ($m = 1; $m <= 12; ++$m) {
            $correction_cost = 0;
            $modified_percentage = null;
            foreach ($r1 as $correction) {

                if ($m == $correction['month']) {
                    $correction_cost = $correction['final'];
                    if (!empty($correction['percent'])) {
                        $modified_percentage = $correction['percent'];
                    }
                }
            };
            if ($m == 1) {
                $percentage = 1;
            } else {
                $percentage = (!empty($modified_percentage)) ? $modified_percentage : $default_percent;
            }

            $month_cost = $base_month_cost * $percentage + $correction_cost;
            $base_month_cost = $month_cost;

            // echo date('F', mktime(0, 0, 0, $m, 1)) . " :: $month_cost :: " . $correction_cost . "  :: " . $percentage . "%<BR>";
            $fcfval[] = round($month_cost, 2);

        }

        return $fcfval;
    }

    public function usun_korekte()
    {

        $message = "";
        try {
            $this->db->trans_begin();

            $id = $this->input->post("target"); // id wydatku


            if (empty($id) || !is_numeric($id)) {
                $message = "Wystąpił błąd";
            }

            /* Pobierz dane wydatku */
            $this->db->select("PK_cor");
            $this->db->where('PK_cor', $id);
            $this->db->from("forecast_correction");
            $query = $this->db->get();


            $tablica_wydatkow = $query->result_array();


            if (empty($tablica_wydatkow[0]["PK_cor"])) {
                $message = "Nie udało się pobrać kwoty podatku";
            }

            /* Delete */

            $this->db->where('PK_cor', $id);
            $this->db->limit(1);
            $this->db->delete('forecast_correction');


            if ($this->db->trans_status() === FALSE || strlen($message) > 0) {
                $this->db->trans_rollback();
                $status = 0;
            } else {
                $this->db->trans_commit();
                $message = "Usunięto";
                $status = 1;
            }
        } catch (Exception $e) {
            throw new Exception("");
            $this->db->trans_rollback();
        }


        return $this->output
            ->set_content_type('application/json')
            ->set_status_header(200)
            ->set_output(json_encode(array("response" => array("status" => $status, "message" => $message))));
    }

    /* Koniec analityki */
    public function StatystykiPracownika_Wartosci($p)
    {
        $query = $this->db->query("
             SELECT cat.id_kat as cat_id,
           cat.nazwa as Category,
           coalesce(month0.tot, 0) AS ThisMonth,
           coalesce(month1.tot, 0) AS LastMonth,
           coalesce(month2.tot, 0) AS PrevMonth
           
    FROM wydatki_kategorie cat
    LEFT JOIN
      (SELECT wydatki_wpisy.kategoria,
              sum(wydatki_wpisy.brutto) AS tot
       FROM wydatki_wpisy
       LEFT JOIN wydatki ON wydatki_wpisy.do_wydatku = wydatki.id_wydatku
       WHERE wydatki.data_zakupu >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH
         AND wydatki.id_kupujacy = " . $p . "
       GROUP BY wydatki_wpisy.kategoria
       ) month0 ON cat.id_kat = month0.kategoria
    LEFT JOIN
      (SELECT wydatki_wpisy.kategoria,
              sum(wydatki_wpisy.brutto) AS tot
       FROM wydatki_wpisy
       LEFT JOIN wydatki ON wydatki_wpisy.do_wydatku = wydatki.id_wydatku
       WHERE wydatki.data_zakupu >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 2 MONTH
         AND wydatki.data_zakupu  < LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 1 MONTH
         AND wydatki.id_kupujacy = " . $p . "
       GROUP BY wydatki_wpisy.kategoria
      ) month1 ON cat.id_kat = month1.kategoria
     LEFT JOIN
      (SELECT wydatki_wpisy.kategoria,
              sum(wydatki_wpisy.brutto) AS tot
       FROM wydatki_wpisy
       LEFT JOIN wydatki ON wydatki_wpisy.do_wydatku = wydatki.id_wydatku
       WHERE wydatki.data_zakupu >= LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 3 MONTH
         AND wydatki.data_zakupu  < LAST_DAY(CURRENT_DATE()) + INTERVAL 1 DAY - INTERVAL 2 MONTH
         AND wydatki.id_kupujacy = " . $p . "
       GROUP BY wydatki_wpisy.kategoria
      ) month2 ON cat.id_kat = month2.kategoria
            ");

        return $query->result_array();
    }

    public function polygonWydatkiKategorie($tytul, $pola, $wartosci, $wartosci_last, $wartosci_blast, $seria, $single = FALSE)
    {
        $data['i_title'] = $tytul;
        $data['i_cat'] = Statistic_model::array2string($pola);
        $data['i_data'] = Statistic_model::array2string($wartosci, FALSE);
        if (!$single) {
            $data['i_data_1'] = Statistic_model::array2string($wartosci_last, FALSE);
            $data['i_data_2'] = Statistic_model::array2string($wartosci_blast, FALSE);
        }

        $data['i_seria'] = $seria;

        $this->load->view("wykresy/polygon", $data);
    }

}
