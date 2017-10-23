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

    /*
     * CACHE QUERY
     */

    public function Ilezostalodootrzymania($nabywcy)
    {
        $query = $this->db->query("SELECT id_platnosci,pozostala_kwota,`przychody`.`numer` FROM `przychody_platnosci`
JOIN przychody ON przychody_platnosci.fk_przychodu = przychody.id_przychodu
WHERE `przychody`.`fk_kontrahent` = " . $nabywcy . " AND pozostala_kwota > 0
Union ALL
SELECT 'SUMA' id_platnosci, SUM(pozostala_kwota), 'blank' FROM `przychody_platnosci`
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
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'] . '-' . $_POST['customMonth'] . '-01';

            $sql3 .= " WHERE ";
            $sql3 .= "wydatki.data_zakupu >= '" . date('Y-m-01', strtotime($query_date)) . "' AND ";
            $sql3 .= "wydatki.data_zakupu <= '" . date('Y-m-t', strtotime($query_date)) . "' AND ";
            $sql3 .= "wydatki.id_kupujacy = '".$p."' ";
            $sql3 .= "";
        }

        $query = $this->db->query($sql . "" . $sql3 . "" . $sql2);

        return $query->result_array();
    }

    public function StatystykiPracownika_Wartosci($p) {
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
