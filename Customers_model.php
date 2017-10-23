<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Customers_model extends CI_Model {

    var $table = 'platnosci';
    var $column_order = array(
        null,
        'rejont',
        'kupujacy',
        'kwota_brutto',
        'kwota_netto',
        'dokument',
        'data_zakupu',
        'ddif',
        'kontrah',
        null,
        null,
        'wartosc_vat',
        'procent_vat',
        null,
        'kat',
        'pozostala_kwota',
        'zaplacona_kwota',
    ); //set column field database for datatable orderable
    var $column_search = array(); //set column field database for datatable searchable 
    var $order = array('termin_platnosci' => 'asc'); // default order 

    public function __construct() {
        parent::__construct();
        $this->load->database();
    }

    function rangeMonth($datestr) {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('Y-m-d', strtotime('first day of this month', $dt));
        $res['end'] = date('Y-m-d', strtotime('last day of this month', $dt));
        return $res;
    }

    function rangeWeek($datestr) {
        date_default_timezone_set(date_default_timezone_get());
        $dt = strtotime($datestr);
        $res['start'] = date('N', $dt) == 1 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('last monday', $dt));
        $res['end'] = date('N', $dt) == 7 ? date('Y-m-d', $dt) : date('Y-m-d', strtotime('next sunday', $dt));
        return $res;
    }

    private function _get_datatables_query() {

        $this->db->select("*,datediff(`platnosci`.`termin_platnosci`,NOW()) as ddif,rejony.nazwa as rejont,CONCAT(pracownicy.imie,' ',pracownicy.nazwisko) as kupujacy,"
                . "kontrahenci.nazwa as kontrah,wydatki_kategorie.nazwa as kat,platnosci.fk_rozbita as rozbita,"
                . "`platnosci`.`termin_platnosci` as termin,`platnosci`.`priorytet` as priorytet");
        $this->db->join('wydatki', 'platnosci.fk_wydatek = wydatki.id_wydatku');
        $this->db->join('rejony', 'wydatki.id_rejonu = rejony.id_rejonu', 'left');
        $this->db->join('pracownicy', 'wydatki.id_kupujacy = pracownicy.id_pracownika', 'left');
        $this->db->join('kontrahenci', 'wydatki.kontrahent = kontrahenci.id_kontrahenta', 'left');
        $this->db->join('wydatki_kategorie', 'wydatki.kategoria = wydatki_kategorie.id_kat', 'left');

        //add custom filter here s_narzecz s_kontrakt
        if ($this->input->post('s_rejon')) {
            $this->db->where('`rejony`.`id_rejonu`', $this->input->post('s_rejon'));
        }
        if ($this->input->post('s_narzecz')) {
            $this->db->where('`wydatki`.`fk_narzecz`', $this->input->post('s_narzecz'));
        }
        if ($this->input->post('s_nrfaktury')) {
            $this->db->like('`wydatki`.`dokument`', $this->input->post('s_nrfaktury'));
        }
        if ($this->input->post('s_kontrakt')) {
            $this->db->where('`wydatki`.`fk_kontrakt`', $this->input->post('s_kontrakt'));
        }

        if ($this->input->post('kupiec')) {
            $this->db->where('wydatki.id_kupujacy', $this->input->post('kupiec'));
        }
        if ($this->input->post('s_kategoria')) {
            $this->db->where('wydatki.kategoria', $this->input->post('s_kategoria'));
        }
        if ($this->input->post('s_kontrahent')) {
            $this->db->where('wydatki.kontrahent', $this->input->post('s_kontrahent'));
        }
        if ($this->input->post('pusteSkany')) {
            $this->db->where('skan_id IS NULL', null, false);
        }
        // zakres dat

        $zakres = $this->input->post('s_zakres');

        // checkboxy

        $chkbox = $this->input->post('status_pl');

        $s_metody = $this->input->post('s_metody');
        if (!empty($s_metody)) {
            $this->db->group_start();
            foreach ($s_metody as $param) {
                //$counter++;

                switch ($param) {
                    case "1" :
                        $this->db->where('`wydatki`.`metoda_platnosci`', '1');
                        break;
                    case "2" :
                        $this->db->or_group_start();
                        $this->db->where('`wydatki`.`metoda_platnosci`', '2');
                        $this->db->group_end();
                        break;
                    case "3" :
                        $this->db->or_group_start();
                        $this->db->where('`wydatki`.`metoda_platnosci`', '3');
                        $this->db->group_end();
                        break;
                }
            }
            $this->db->group_end();
        }
        if (!empty($chkbox)) {
            $this->db->group_start();
            foreach ($chkbox as $param) {
                //$counter++;

                switch ($param) {
                    case "zap" :
                        $this->db->where('`platnosci`.`pozostala_kwota`', '0');
                        break;
                    case "do_zap" :
                        $this->db->or_group_start();
                        $this->db->where('datediff(`platnosci`.`termin_platnosci`,NOW())  >=', '0');
                        $this->db->where('`platnosci`.`pozostala_kwota`  >', '0');
                        $this->db->group_end();
                        break;
                    case "po_term" :
                        $this->db->or_group_start();
                        $this->db->where('datediff(`platnosci`.`termin_platnosci`,NOW())  <', '0');
                        $this->db->where('`platnosci`.`pozostala_kwota`  >', '0');
                        $this->db->group_end();
                        break;
                }
            }
            $this->db->group_end();
        }
        if ((isset($_POST['customMonth']) && $_POST['customMonth'] >= 1 && $_POST['customMonth'] <= 12) &&
            (isset($_POST['customYear']) && $_POST['customYear'] >= 2017 && $_POST['customMonth'] <= 2050)) {

            $query_date = $_POST['customYear'].'-' . $_POST['customMonth'] . '-01';

            $this->db->group_start();
            $this->db->where('wydatki.data_zakupu >=', date('Y-m-01', strtotime($query_date)));
            $this->db->where('wydatki.data_zakupu <=', date('Y-m-t', strtotime($query_date)));
            $this->db->group_end();
        }


        switch ($zakres) {
            case "today" :
                $this->db->group_start();
                $today = date('Y-m-d');
                $this->db->where('wydatki.data_zakupu', $today);
                $this->db->group_end();
                break;
            case "yesterday" :
                $this->db->group_start();
                $yesterday = date('Y-m-d', strtotime("-1 days"));
                $this->db->where('wydatki.data_zakupu', $yesterday);
                $this->db->group_end();
                break;
            case "this_month" :
                $this->db->group_start();
                $range = $this->rangeMonth(date('Y-m-d'));
                $this->db->where('wydatki.data_zakupu >=', $range['start']);
                $this->db->where('wydatki.data_zakupu <=', $range['end']);
                $this->db->group_end();

                break;
            case "this_year" :
                $this->db->group_start();
                $this->db->where('wydatki.data_zakupu >=', date('l', strtotime(date('Y-01-01'))));
                $this->db->group_end();
                break;
            case "Q1" :
                $this->db->group_start();
                $this->db->where('wydatki.data_zakupu >= ', date('Y-m-d', strtotime('first day of january this year')));
                $this->db->where('wydatki.data_zakupu <= ', date('Y-m-d', strtotime('last day of march this year')));
                $this->db->group_end();
                break;
            case "Q2" :
                $this->db->group_start();
                $this->db->where('wydatki.data_zakupu >= ', date('Y-m-d', strtotime('first day of april this year')));
                $this->db->where('wydatki.data_zakupu <= ', date('Y-m-d', strtotime('last day of june this year')));
                $this->db->group_end();
                break;
            case "Q3" :
                $this->db->group_start();
                $this->db->where('wydatki.data_zakupu >= ', date('Y-m-d', strtotime('first day of july this year')));
                $this->db->where('wydatki.data_zakupu <= ', date('Y-m-d', strtotime('last day of september this year')));
                $this->db->group_end();
                break;
            case "Q4" :
                $this->db->group_start();
                $this->db->where('wydatki.data_zakupu >= ', date('Y-m-d', strtotime('first day of october this year')));
                $this->db->where('wydatki.data_zakupu <= ', date('Y-m-d', strtotime('last day of december this year')));
                $this->db->group_end();
                break;
            case "last_week" :
                $this->db->group_start();
                $lw = date('Y-m-d', strtotime("-7 days"));
                $range = $this->rangeMonth($lw);
                $this->db->where('wydatki.data_zakupu >=', $range['start']);
                $this->db->where('wydatki.data_zakupu <=', $range['end']);
                $this->db->group_end();
                break;
            case "last_month" :
                $this->db->group_start();
                $this->db->where('wydatki.data_zakupu >=', date('Y-m-d', strtotime('first day of last month')));
                $this->db->where('wydatki.data_zakupu <=', date('Y-m-d', strtotime('last day of last month')));
                $this->db->group_end();
                break;
            case "custom" :
                // todo custom
                $sd = $this->input->post("dateFrom");
                $fd = $this->input->post("dateTo");
                if (!empty($sd) && !empty($fd)) {
                    $this->db->group_start();
                    $this->db->where('wydatki.data_zakupu >=', $sd);
                    $this->db->where('wydatki.data_zakupu <=', $fd);
                    $this->db->group_end();
                }
                break;
        }


        $this->db->from($this->table);




        if (isset($_POST['order'])) { // here order processing
            $this->db->order_by($this->column_order[$_POST['order']['0']['column']], $_POST['order']['0']['dir']);
        } else if (isset($this->order)) {
            $order = $this->order;
            $this->db->order_by(key($order), $order[key($order)]);
        }
    }

    public function get_datatables() {
        $this->_get_datatables_query();
        $skip = FALSE;
        if (empty($this->input->post('length'))) {
            $l = 10;
            $s = 0;
        } else {
            $l = $this->input->post('length');
            $s = $this->input->post('start');
        }if ($this->input->post('length') == -1) {
            $skip = TRUE;
        }

        if (!$skip) {
            $this->db->limit($l, $s);
        }


        $query = $this->db->get();
        return $query->result();
    }

    /*
     *     ["id_platnosci"]=>
      string(3) "249"
      ["dodano"]=>
      string(19) "2017-08-03 08:53:02"
      ["utworzenie_platnosci"]=>
      string(10) "2017-08-03"
      ["termin_platnosci"]=>
      string(10) "2017-08-10"
      ["status"]=>
      string(1) "1"
      ["zaplacona_kwota"]=>
      string(5) "11.00"
      ["pozostala_kwota"]=>
      string(6) "123.00"
      ["fk_wydatek"]=>
      string(2) "30"
      ["priorytet"]=>
      string(1) "3"
      ["fk_rozbita"]=>
      string(1) "0"
      ["id_wydatku"]=>
      string(2) "30"
      ["id_rejonu"]=>
      string(1) "3"
      ["id_kupujacy"]=>
      string(1) "1"
      ["kwota_brutto"]=>
      string(6) "123.00"
      ["kwota_netto"]=>
      string(6) "100.00"
      ["dokument"]=>
      string(3) "RND"
      ["data_zakupu"]=>
      string(10) "2017-08-03"
      ["kontrahent"]=>
      string(1) "1"
      ["cel_zakupu"]=>
      string(0) ""
      ["dodal"]=>
      string(1) "1"
      ["utworzono"]=>
      string(19) "2017-08-03 08:53:02"
      ["skan_id"]=>
      string(1) "0"
      ["wartosc_vat"]=>
      string(5) "23.00"
      ["procent_vat"]=>
      string(2) "23"
      ["metoda_platnosci"]=>
      string(1) "1"
      ["kategoria"]=>
      string(1) "1"
      ["nazwa"]=>
      string(7) "Usługi"
      ["id_pracownika"]=>
      string(1) "1"
      ["fk_rejon"]=>
      string(1) "1"
      ["fk_adres"]=>
      string(1) "4"
      ["imie"]=>
      string(9) "Pracownik"
      ["nazwisko"]=>
      string(1) "1"
      ["telefon_sluzbowy"]=>
      string(0) ""
      ["telefon_prywatny"]=>
      string(0) ""
      ["konto"]=>
      string(26) "55114020040000360270485519"
      ["id_kontrahenta"]=>
      string(1) "1"
      ["id_kat"]=>
      string(1) "1"
      ["rejont"]=>
      string(4) "Kraj"
      ["kupujacy"]=>
      string(11) "Pracownik 1"
      ["kontrah"]=>
      string(6) "Inglot"
      ["kkat"]=>
      string(7) "Usługi"
      ["rozbita"]=>
      string(1) "0"
      ["termin"]=>
      string(10) "2017-08-10"
     */

    public function agregacja($get) {

        /*
          pozostala_kwota
          zaplacona_kwota
          kwota_brutto
          wartosc_vat
          fk_rejon
          metoda_platnosci
          priorytet
          status

          switch ($statusi) {
          case 1:
          // do zapłaty
          $dozaplaty = 0;
          $oplacono = $brutto;
          break;
          case 2:
          // opłacony
          $dozaplaty = $brutto;
          $oplacono = 0;
          break;
          case 3:
          // częściowo opłacony
          $dozaplaty = $brutto - $ileoplacono;
          $oplacono = $ileoplacono;
          break;
          default:
          break;
          }

         */
        $lacznie_netto = 0;
        $lacznie_brutto = 0;
        $pozostala_kwota = 0;
        $zaplacona_kwota = 0;
        $status_do_zaplaty = 0;
        $status_oplacone = 0;
        $status_czesciowo = 0;
        $wartosc_vat = 0;
        $po_terminie = 0;
        if (!empty($get)) {
            foreach ($get as $a) {
                $lacznie_netto = bcadd($lacznie_netto, $a['kwota_netto'], 2);
                $lacznie_brutto = bcadd($lacznie_brutto, $a['kwota_brutto'], 2);
                $pozostala_kwota = bcadd($pozostala_kwota, $a['pozostala_kwota'], 2);
                $zaplacona_kwota = bcadd($zaplacona_kwota, $a['zaplacona_kwota'], 2);
                $wartosc_vat = bcadd($wartosc_vat, $a['wartosc_vat'], 2);

                switch ($a['status']) {
                    case 1:
                        // do zapłaty
                        $status_do_zaplaty = bcadd($status_do_zaplaty, 1);
                        break;
                    case 2:
                        // opłacony
                        $status_oplacone = bcadd($status_oplacone, 1);
                        break;
                    case 3:
                        // częściowo opłacony
                        $status_czesciowo = bcadd($status_czesciowo, 1);
                        break;
                    default:
                        break;
                }

                $r_dni = intval($a['ddif']);

                if (floatval($a['pozostala_kwota']) > 0) {
                    if ($r_dni < 0) {
                        // po terminie
                        $po_terminie++;
                        //echo $get['id_platnosci'];
                    }

                    if ($a['ddif'] == 1) {
                        // jutro platnosc
                    }

                    if ($a['ddif'] == 0) {
                        // dzisiaj platnosc
                    }
                }
            }
            //var_dump($get);
        }

        return
                array(
                    'netto' => $lacznie_netto,
                    'brutto' => $lacznie_brutto,
                    'pozostala_kwota' => $pozostala_kwota,
                    'zaplacona_kwota' => $zaplacona_kwota,
                    'vat' => $wartosc_vat,
                    'po_terminie' => $po_terminie,
                    'status' => array(
                        "do_zaplaty" => $status_do_zaplaty,
                        "oplacone" => $status_oplacone,
                        "czesciowo" => $status_czesciowo
                    )
        );
    }

    public function count_filtered() {

        $this->_get_datatables_query();
        $query = $this->db->get();
        return array(
            'count' => $query->num_rows(),
            'agregacja' => $this->agregacja($query->result_array())
        );
    }

    public function count_all() {
        $this->db->from($this->table);
        return $this->db->count_all_results();
    }

    public function get_list_countries() {


        $countries = array();

        return $countries;
    }

}
